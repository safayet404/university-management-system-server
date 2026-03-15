<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications — paginated list for current user
    public function index(Request $request)
    {
        $query = Notification::forUser(auth()->id())
            ->with('sender:id,name,avatar')
            ->latest();

        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('type'))     $query->where('type', $request->type);
        if ($request->filled('is_read'))  $query->where('is_read', $request->boolean('is_read'));

        $notifications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success'      => true,
            'data'         => $notifications->map(fn($n) => $this->format($n)),
            'unread_count' => Notification::forUser(auth()->id())->unread()->count(),
            'pagination'   => ['total' => $notifications->total(), 'current_page' => $notifications->currentPage(), 'last_page' => $notifications->lastPage()],
        ]);
    }

    // GET /api/notifications/unread-count
    public function unreadCount()
    {
        return response()->json([
            'success' => true,
            'count'   => Notification::forUser(auth()->id())->unread()->count(),
        ]);
    }

    // GET /api/notifications/recent — latest 10 for dropdown
    public function recent()
    {
        $notifications = Notification::forUser(auth()->id())
            ->with('sender:id,name')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success'      => true,
            'data'         => $notifications->map(fn($n) => $this->format($n)),
            'unread_count' => Notification::forUser(auth()->id())->unread()->count(),
        ]);
    }

    // PATCH /api/notifications/{id}/read
    public function markRead($id)
    {
        $notification = Notification::forUser(auth()->id())->findOrFail($id);
        $notification->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['success' => true, 'unread_count' => Notification::forUser(auth()->id())->unread()->count()]);
    }

    // PATCH /api/notifications/mark-all-read
    public function markAllRead()
    {
        Notification::forUser(auth()->id())->unread()->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    // DELETE /api/notifications/{id}
    public function destroy($id)
    {
        Notification::forUser(auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // DELETE /api/notifications/clear-all
    public function clearAll()
    {
        Notification::forUser(auth()->id())->where('is_read', true)->delete();
        return response()->json(['success' => true, 'message' => 'Read notifications cleared.']);
    }

    // POST /api/notifications/send — admin sends to users
    public function send(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'message'      => 'required|string',
            'type'         => 'required|in:info,success,warning,error,announcement',
            'category'     => 'required|string',
            'target'       => 'required|in:all,role,user',
            'role'         => 'required_if:target,role|string',
            'user_ids'     => 'required_if:target,user|array',
            'action_url'   => 'nullable|string',
            'action_label' => 'nullable|string',
        ]);

        $sentBy  = auth()->id();
        $options = ['sent_by' => $sentBy, 'action_url' => $request->action_url, 'action_label' => $request->action_label];

        if ($request->target === 'all') {
            $userIds = User::pluck('id')->toArray();
            Notification::notifyMany($userIds, $request->type, $request->category, $request->title, $request->message, $options);
            $count = count($userIds);
        } elseif ($request->target === 'role') {
            $userIds = User::role($request->role)->pluck('id')->toArray();
            Notification::notifyMany($userIds, $request->type, $request->category, $request->title, $request->message, $options);
            $count = count($userIds);
        } else {
            Notification::notifyMany($request->user_ids, $request->type, $request->category, $request->title, $request->message, $options);
            $count = count($request->user_ids);
        }

        return response()->json(['success' => true, 'message' => "Notification sent to {$count} users."]);
    }

    // GET /api/notifications/stats
    public function stats()
    {
        $userId = auth()->id();
        return response()->json(['success' => true, 'data' => [
            'total'       => Notification::forUser($userId)->count(),
            'unread'      => Notification::forUser($userId)->unread()->count(),
            'today'       => Notification::forUser($userId)->whereDate('created_at', today())->count(),
            'by_category' => Notification::forUser($userId)->selectRaw('category, count(*) as count')->groupBy('category')->get(),
        ]]);
    }

    private function format(Notification $n): array
    {
        return [
            'id'           => $n->id,
            'type'         => $n->type,
            'category'     => $n->category,
            'title'        => $n->title,
            'message'      => $n->message,
            'action_url'   => $n->action_url,
            'action_label' => $n->action_label,
            'is_read'      => $n->is_read,
            'read_at'      => $n->read_at?->format('Y-m-d H:i:s'),
            'created_at'   => $n->created_at?->format('Y-m-d H:i:s'),
            'time_ago'     => $n->created_at?->diffForHumans(),
            'sender'       => $n->sender ? ['id' => $n->sender->id, 'name' => $n->sender->name] : null,
        ];
    }
}
