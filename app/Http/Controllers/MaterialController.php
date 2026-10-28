<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Classroom;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    use AuthorizesClassroom;

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'link' => ['nullable', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'max:10240'], // 10 MB
        ]);

        $material = new Material([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'link' => $data['link'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $material->file_path = $file->store('materials', 'local');
            $material->file_name = $file->getClientOriginalName();
        }

        $classroom->materials()->save($material);

        return redirect()->route('classrooms.classwork', $classroom)->with('success', 'Materi ditambahkan.');
    }

    public function download(Material $material): StreamedResponse
    {
        $this->authorizeAccess($material->classroom);
        abort_unless($material->file_path && Storage::disk('local')->exists($material->file_path), 404);

        return Storage::disk('local')->download($material->file_path, $material->file_name);
    }

    public function destroy(Material $material): RedirectResponse
    {
        $this->authorizeManage($material->classroom);

        if ($material->file_path) {
            Storage::disk('local')->delete($material->file_path);
        }
        $material->delete();

        return back()->with('success', 'Materi dihapus.');
    }
}
