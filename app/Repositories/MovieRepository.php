<?php

namespace App\Repositories;

use App\Models\Movia;

class MovieRepository
{
    public function getAllMovies()
    {
        return Movia::all();
    }

    public function create(array $data)
    {
        return Movia::create($data);
    }

    public function delete(int $id)
    {
        return Movia::destroy($id);
    }

    // Kino kodiga qarab topish
    public function getByCode(string $code)
    {
        return Movia::where('code', $code)->first();
    }
}