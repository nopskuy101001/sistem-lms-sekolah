<?php

namespace App\Http\Controllers;

use App\Services\ChunkedQuestionPackageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChunkedQuestionPackageUploadController extends Controller
{
    public function store(Request $request, ChunkedQuestionPackageUploadService $uploads): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:119'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:120'],
            'original_name' => ['required', 'string', 'max:255'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.ChunkedQuestionPackageUploadService::MAX_BYTES],
            'chunk' => ['required', 'file', 'max:1536'],
        ]);

        return response()->json($uploads->accept(
            $request->user(), $request->file('chunk'), $data['upload_id'],
            (int) $data['chunk_index'], (int) $data['total_chunks'],
            $data['original_name'], (int) $data['total_size'],
        ));
    }
}
