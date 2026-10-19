<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Validation\Rules\Password;

/** One password rule for changing and resetting passwords. */
class PasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min((int) Setting::get('min_password_length', 8))->letters()->numbers();
    }
}
