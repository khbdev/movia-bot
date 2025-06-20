<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Movia;
use App\Repositories\ChannelRepository;
use App\Repositories\MovieRepository;
use App\Repositories\UserRepository;

class AdminService
{
    protected UserRepository $userRepository;
    protected ChannelRepository $channelRepository;
    protected MovieRepository $movieRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->channelRepository = new ChannelRepository();
        $this->movieRepository = new MovieRepository();
    }

    public function getAllUsers()
    {
        return $this->userRepository->getAllUsers();
    }

    public function getAllChannels()
    {
        return Channel::orderBy('created_at', 'desc')->get();
    }

    public function addChannel(array $data): bool
    {
        try {
            Channel::create([
                'name' => $data['name'],
                'link' => $data['link'],
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteChannel(int $channelId): bool
    {
        try {
            $channel = Channel::find($channelId);
            if ($channel) {
                $channel->delete();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getAllMovies()
    {
        return Movia::orderBy('created_at', 'desc')->get();
    }

    public function addMovie(array $data): bool
    {
        try {
            Movia::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'raw_post' => $data['raw_post'],
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteMovie(int $movieId): bool
    {
        try {
            $movie = Movia::find($movieId);
            if ($movie) {
                $movie->delete();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}