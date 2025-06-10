<?php

namespace App\Services;

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
        $this->userRepository = new UserRepository;
        $this->channelRepository = new ChannelRepository;
        $this->movieRepository = new MovieRepository; // Qo'shilgan qator
    }

    public function getAllUsers()
    {
        return $this->userRepository->getAllUsers();
    }

    public function getAllChannels()
    {
        return $this->channelRepository->getAllChannels();
    }

    public function addChannel(array $data)
    {
        return $this->channelRepository->create($data);
    }

    public function deleteChannel(int $id)
    {
        return $this->channelRepository->delete($id);
    }

    public function getAllMovies()
    {
        return $this->movieRepository->getAllMovies();
    }

    public function addMovie(array $data)
    {
        return $this->movieRepository->create($data);
    }

    public function deleteMovie(int $id)
    {
        return $this->movieRepository->delete($id);
    }
}
