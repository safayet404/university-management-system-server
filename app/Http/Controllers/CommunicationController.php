<?php

namespace App\Http\Controllers;

use App\Mail\DirectMessage;
use App\Models\SentEmail;
use App\Models\StudentProfile;
use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Personal emails written by staff to one person: a system user, a student's
 * guardian, or any address typed in by hand. Every send is logged, and each
 * sender has a daily cap so the institution's mail account cannot be used to
 * spam.
 */
class CommunicationController extends Controller
{
    use LogsPageVisit;

    // GET /api/communications/recipients?q=
    public function recipients(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (Str::length($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $users = User::with('roles')
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
            ->limit(8)->get()
            ->map(fn ($u) => [
                'type'    => 'user',
                'user_id' => $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'label'   => ucfirst(str_replace('-', ' ', $u->roles->first()?->name ?? 'user')),
            ]);

        $guardians = StudentProfile::with('user')
            ->whereNotNull('guardian_email')
            ->where(fn ($w) => $w->where('guardian_name', 'like', "%{$q}%")
                ->orWhere('guardian_email', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")))
            ->limit(8)->get()
            ->map(fn ($s) => [
                'type'    => 'guardian',
                'user_id' => null,
                'name'    => $s->guardian_name ?: 'Guardian of ' . $s->user?->name,
                'email'   => $s->guardian_email,
                'label'   => 'Guardian of ' . $s->user?->name . ($s->student_id ? " ({$s->student_id})" : ''),
            ]);

        return response()->json(['success' => true, 'data' => $users->concat($guardians)->values()]);
    }

    // POST /api/communications/email
    public function send(Request $request)
    {
        $data = $request->validate([
            'to'                => 'required|email:rfc|max:255',
            'recipient_name'    => 'nullable|string|max:255',
            'recipient_type'    => 'nullable|in:user,guardian,external',
            'recipient_user_id' => 'nullable|exists:users,id',
            'subject'           => 'required|string|max:200',
            'message'           => 'required|string|max:10000',
        ]);

        $sender = $request->user();

        $limit     = (int) config('app.mail_daily_limit_per_user');
        $sentToday = SentEmail::where('sender_id', $sender->id)->where('created_at', '>=', now()->startOfDay())->count();
        if ($sentToday >= $limit) {
            return response()->json(['success' => false, 'message' => "You can send up to {$limit} emails a day. Please try again tomorrow."], 429);
        }

        $domain  = Str::lower(Str::after($data['to'], '@'));
        $allowed = config('app.mail_allowed_domains');
        if ($allowed && ! in_array($domain, $allowed, true)) {
            return response()->json(['success' => false, 'message' => "Emails to @{$domain} are not allowed by this institution."], 422);
        }

        $log = SentEmail::create([
            'sender_id'         => $sender->id,
            'recipient_email'   => $data['to'],
            'recipient_name'    => $data['recipient_name'] ?? null,
            'recipient_type'    => $data['recipient_type'] ?? 'external',
            'recipient_user_id' => $data['recipient_user_id'] ?? null,
            'subject'           => $data['subject'],
            'body'              => $data['message'],
        ]);

        try {
            Mail::to($data['to'], $data['recipient_name'] ?? null)
                ->send(new DirectMessage($sender, $data['recipient_name'] ?? '', $data['subject'], $data['message']));
        } catch (Throwable $e) {
            report($e);
            $log->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 250)]);

            return response()->json(['success' => false, 'message' => 'The email could not be sent. Please try again later.'], 502);
        }

        self::logVisit('communications', 'email', 'created', "Emailed {$data['to']}: {$data['subject']}", [], [], SentEmail::class, $log->id);

        return response()->json([
            'success'   => true,
            'message'   => "Email sent to {$data['to']}.",
            'remaining' => max(0, $limit - $sentToday - 1),
        ], 201);
    }

    // GET /api/communications/email
    public function index(Request $request)
    {
        $query = SentEmail::with('sender')->latest();

        // Without the read permission, a sender only sees their own messages
        if (! $request->user()->can('communications.read')) {
            $query->where('sender_id', $request->user()->id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($w) => $w->where('recipient_email', 'like', "%{$s}%")->orWhere('subject', 'like', "%{$s}%"));
        }

        $emails = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $emails->map(fn ($e) => [
                'id'             => $e->id,
                'to'             => $e->recipient_email,
                'recipient_name' => $e->recipient_name,
                'recipient_type' => $e->recipient_type,
                'subject'        => $e->subject,
                'body'           => $e->body,
                'status'         => $e->status,
                'sender'         => $e->sender?->name,
                'sent_at'        => $e->created_at?->format('Y-m-d H:i'),
            ]),
            'pagination' => ['total' => $emails->total(), 'current_page' => $emails->currentPage(), 'last_page' => $emails->lastPage()],
            'daily_limit' => (int) config('app.mail_daily_limit_per_user'),
            'sent_today'  => SentEmail::where('sender_id', $request->user()->id)->where('created_at', '>=', now()->startOfDay())->count(),
        ]);
    }
}
