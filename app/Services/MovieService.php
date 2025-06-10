<?php

namespace App\Services;

use App\Repositories\MovieRepository;

class MovieService
{
    protected MovieRepository $movieRepository;

    public function __construct()
    {
        $this->movieRepository = new MovieRepository();
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

    // Kino kodiga qarab topish
public function findMovieByCode(string $code): ?array
{
    $movie = $this->movieRepository->getByCode($code);

    if (!$movie || empty($movie->raw_post)) {
        return null;
    }

    // Faqat raw_post JSON sifatida qaytariladi
    $postData = json_decode($movie->raw_post, true);

    return is_array($postData) ? $postData : null;
}
}