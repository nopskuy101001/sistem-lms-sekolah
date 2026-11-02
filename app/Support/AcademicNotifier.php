<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\User;
use App\Notifications\AcademicNotification;
use Illuminate\Support\Facades\Notification;

class AcademicNotifier
{
    public static function students(
        Classroom $classroom,
        string $title,
        string $message,
        string $url,
        string $category,
    ): void {
        $recipients = $classroom->students()->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AcademicNotification($title, $message, $url, $category));
        }
    }

    public static function teacher(
        Classroom $classroom,
        string $title,
        string $message,
        string $url,
        string $category,
    ): void {
        $teacher = $classroom->teacher()->first();

        if ($teacher) {
            self::user($teacher, $title, $message, $url, $category);
        }
    }

    public static function user(
        User $user,
        string $title,
        string $message,
        string $url,
        string $category,
    ): void {
        $user->notify(new AcademicNotification($title, $message, $url, $category));
    }
}
