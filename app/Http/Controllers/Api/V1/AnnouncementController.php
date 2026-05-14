<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * GET /api/v1/announcements
     *
     * Returns currently active announcements for all audiences or teachers.
     *
     * Query params:
     *   audience — all|teachers|parents|students (default: all)
     *   per_page — default 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::active()->latest('published_at');

        if ($audience = $request->get('audience')) {
            $query->where('audience', $audience);
        }

        $announcements = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $announcements->map(fn ($a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'audience'     => $a->audience,
                'published_at' => $a->published_at?->toIso8601String(),
                'expires_at'   => $a->expires_at?->toIso8601String(),
            ]),
            'meta' => [
                'total'        => $announcements->total(),
                'per_page'     => $announcements->perPage(),
                'current_page' => $announcements->currentPage(),
                'last_page'    => $announcements->lastPage(),
            ],
        ]);
    }
}
