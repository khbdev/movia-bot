<?php

// app/Services/UserService.php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Carbon\Carbon;

class UserService
{
    protected UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository;

    }

    public function createOrGetUser(array $data): User
    {
        $user = User::where('telegram_id', $data['telegram_id'])->first();

        if ($user) {
            return $user;
        }

        $role = $data['telegram_id'] == env('ADMIN_CHAT_ID') ? 'admin' : 'user';

        return User::create([
            'telegram_id' => $data['telegram_id'],
            'username' => $data['username'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $role,
            'registered_at' => Carbon::now(),
        ]);
    }

    public function getAllUsers()
    {
        return $this->userRepository->getAllUsers();
    }
}
