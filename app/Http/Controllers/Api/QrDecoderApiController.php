<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\QrDecoderController as WebQrDecoderController;
use Illuminate\Http\Request;

class QrDecoderApiController extends Controller
{
    public function decodeFromImage(Request $request)
    {
        return app(WebQrDecoderController::class)->decodeFromImage($request);
    }
}

