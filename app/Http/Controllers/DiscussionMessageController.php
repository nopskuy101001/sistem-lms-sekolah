<?php

namespace App\Http\Controllers;

use App\Models\DiscussionMessage;
use App\Models\DiscussionRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscussionMessageController extends Controller
{
    public function store(Request $request, DiscussionRoom $discussionRoom): RedirectResponse
    {
        $this->authorizeAccess($request, $discussionRoom);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000', 'required_without:attachment'],
            'attachment' => [
                'nullable',
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip',
            ],
        ]);

        $messageData = [
            'user_id' => $request->user()->id,
            'body' => filled($data['body'] ?? null) ? trim($data['body']) : null,
        ];
        $storedPath = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $storedPath = $file->store('discussion-attachments', 'local');
            $messageData += [
                'attachment_path' => $storedPath,
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime' => $file->getMimeType() ?: $file->getClientMimeType(),
                'attachment_size' => $file->getSize(),
            ];
        }

        try {
            $discussionRoom->messages()->create($messageData);
            $discussionRoom->touch();
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return back()->with('success', 'Pesan terkirim.');
    }

    public function attachment(Request $request, DiscussionMessage $discussionMessage): StreamedResponse
    {
        $this->authorizeAccess($request, $discussionMessage->room);
        abort_unless(
            $discussionMessage->attachment_path
                && Storage::disk('local')->exists($discussionMessage->attachment_path),
            404
        );

        $disposition = $request->boolean('download') || ! $discussionMessage->isImage()
            ? 'attachment'
            : 'inline';

        return Storage::disk('local')->response(
            $discussionMessage->attachment_path,
            $discussionMessage->attachment_name,
            ['Cache-Control' => 'private, max-age=3600'],
            $disposition,
        );
    }

    public function destroy(Request $request, DiscussionMessage $discussionMessage): RedirectResponse
    {
        $room = $discussionMessage->room;
        $this->authorizeAccess($request, $room);
        abort_unless(
            $request->user()->isAdmin()
                || $discussionMessage->user_id === $request->user()->id
                || $room->creator_id === $request->user()->id,
            403
        );

        $path = $discussionMessage->attachment_path;
        $discussionMessage->delete();
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', 'Pesan dihapus.');
    }

    private function authorizeAccess(Request $request, DiscussionRoom $room): void
    {
        abort_unless($room->canAccess($request->user()), 403);
    }
}
