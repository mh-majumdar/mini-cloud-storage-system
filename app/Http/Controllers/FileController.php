<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileStore;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileController extends Controller
{
    const MAX_STORAGE = 500 * 1024 * 1024; // 500 MB in bytes

    public function index($user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $files = File::where('user_id', $user_id)
            ->active()
            ->select('id', 'file_name', 'file_size', 'uploaded_at')
            ->orderBy('uploaded_at', 'desc')
            ->get();

        return response()->json([
            'user_id' => (int) $user_id,
            'files' => $files,
        ]);
    }

    public function store(Request $request, $user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $validated = $request->validate([
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1',
            'file_hash' => 'required|string|max:64',
        ]);

        $fileName = $validated['file_name'];
        $fileSize = $validated['file_size'];
        $fileHash = $validated['file_hash'];

        try {
            $file = DB::transaction(function () use ($user_id, $fileName, $fileSize, $fileHash) {

                $lockedFiles = File::where('user_id', $user_id)
                    ->active()
                    ->lockForUpdate()
                    ->get();

                $duplicate = $lockedFiles->firstWhere('file_name', $fileName);
                if ($duplicate) {
                    throw new \Exception('A file with this name already exists.');
                }

                $usedStorage = $lockedFiles->sum('file_size');
                if (($usedStorage + $fileSize) > self::MAX_STORAGE) {
                    $remainingMB = round((self::MAX_STORAGE - $usedStorage) / 1024 / 1024, 2);
                    throw new \Exception(
                        "Storage limit exceeded. You have {$remainingMB} MB remaining."
                    );
                }

                $fileStore = FileStore::where('file_hash', $fileHash)->lockForUpdate()->first();

                if ($fileStore) {
                    $fileStore->increment('ref_count');
                } else {
                    $fileStore = FileStore::create([
                        'file_hash' => $fileHash,
                        'file_size' => $fileSize,
                        'ref_count' => 1,
                    ]);
                }

                return File::create([
                    'user_id' => $user_id,
                    'file_store_id' => $fileStore->id,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'uploaded_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'File uploaded successfully.',
                'file' => [
                    'id' => $file->id,
                    'file_name' => $file->file_name,
                    'file_size' => $file->file_size,
                    'uploaded_at' => $file->uploaded_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy($user_id, $file_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $file = File::where('id', $file_id)
            ->where('user_id', $user_id)
            ->active()
            ->first();

        if (!$file) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        DB::transaction(function () use ($file) {
            $file->update(['deleted_at' => now()]);

            $store = FileStore::where('id', $file->file_store_id)->lockForUpdate()->first();
            if ($store) {
                $store->decrement('ref_count');
                if ($store->ref_count <= 0) {
                    $store->delete();
                }
            }
        });

        return response()->json(['message' => 'File deleted successfully.']);
    }

    public function storageSummary($user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $activeFiles = File::where('user_id', $user_id)->active();

        $totalUsed = $activeFiles->sum('file_size');
        $remaining = self::MAX_STORAGE - $totalUsed;
        $fileCount = $activeFiles->count();

        return response()->json([
            'user_id' => (int) $user_id,
            'storage_limit' => self::formatSize(self::MAX_STORAGE),
            'total_used' => self::formatSize($totalUsed),
            'remaining' => self::formatSize($remaining),
            'total_used_bytes' => $totalUsed,
            'remaining_bytes' => $remaining,
            'total_active_files' => $fileCount,
        ]);
    }

    private static function formatSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
