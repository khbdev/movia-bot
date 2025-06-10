<?php

namespace App\Http\Controllers;

use App\Jobs\HandleTelegramUpdate;
use Illuminate\Http\Request;

class TelegramBotController extends Controller
{
  public function handle(Request $request)
  {
        $update = $request->all();
    HandleTelegramUpdate::dispatch($update); // job ga yuborish
    return response()->json(['ok' => true]);
  }
}
