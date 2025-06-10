<?php

namespace App\Repositories;

use App\Models\Channel;

class ChannelRepository
{
    public function getAllChannels()
    {
        return Channel::all();
    }

    public function create(array $data)
    {
        return Channel::create([
            'name' => $data['name'],
            'link' => $data['link'],
        ]);
    }

    public function delete(int $id)
    {
        return Channel::destroy($id);
    }
}