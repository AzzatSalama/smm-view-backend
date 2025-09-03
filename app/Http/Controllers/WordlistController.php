<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\StreamerWordsLists;

class WordlistController extends Controller
{
    private $wordlistPath = 'private/wordlists';

    public function __construct()
    {
        // Ensure wordlists directory exists
        if (!Storage::exists($this->wordlistPath)) {
            Storage::makeDirectory($this->wordlistPath);
        }
    }

    // Get wordlist content
    public function getWordlist(Request $request, $type)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validTypes = ['kcip', 'gamble'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid wordlist type'], 400);
        }

        $streamer = $user->streamer;
        
        // Find the wordlist record for this streamer and type
        $wordlistRecord = StreamerWordsLists::where('streamer_id', $streamer->id)
            ->where('filename', 'LIKE', "%_{$type}_%")
            ->latest()
            ->first();

        if (!$wordlistRecord) {
            // Return empty content if no wordlist exists
            return response()->json([
                'type' => $type,
                'content' => '',
                'filename' => null
            ]);
        }

        $filePath = "{$this->wordlistPath}/{$wordlistRecord->filename}";

        if (!Storage::exists($filePath)) {
            // File doesn't exist, return empty content
            return response()->json([
                'type' => $type,
                'content' => '',
                'filename' => $wordlistRecord->filename
            ]);
        }

        $content = Storage::get($filePath);
        
        return response()->json([
            'type' => $type,
            'content' => $content,
            'filename' => $wordlistRecord->filename
        ]);
    }

    // Update wordlist content
    public function updateWordlist(Request $request, $type)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validTypes = ['kcip', 'gamble'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid wordlist type'], 400);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:100000'], // Max 100KB
        ]);

        $streamer = $user->streamer;

        try {
            // Check if wordlist already exists for this streamer and type
            $existingWordlist = StreamerWordsLists::where('streamer_id', $streamer->id)
                ->where('filename', 'LIKE', "%_{$type}_%")
                ->latest()
                ->first();

            if ($existingWordlist) {
                // Update existing wordlist
                $filePath = "{$this->wordlistPath}/{$existingWordlist->filename}";
                Storage::put($filePath, $data['content']);
                
                // Update the timestamp
                $existingWordlist->touch();
                
                return response()->json([
                    'message' => 'Wordlist updated successfully',
                    'type' => $type,
                    'filename' => $existingWordlist->filename
                ]);
            } else {
                // Create new wordlist
                $randomFilename = uniqid() . '_' . $type . '_' . time() . '.txt';
                $filePath = "{$this->wordlistPath}/{$randomFilename}";
                
                Storage::put($filePath, $data['content']);
                
                // Save to streamer_wordslists table
                StreamerWordsLists::create([
                    'streamer_id' => $streamer->id,
                    'filename' => $randomFilename
                ]);
                
                return response()->json([
                    'message' => 'Wordlist created successfully',
                    'type' => $type,
                    'filename' => $randomFilename
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update wordlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get all available wordlists
    public function getWordlists(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        $wordlists = [];
        $validTypes = ['kcip', 'gamble'];

        foreach ($validTypes as $type) {
            // Find the latest wordlist record for this streamer and type
            $wordlistRecord = StreamerWordsLists::where('streamer_id', $streamer->id)
                ->where('filename', 'LIKE', "%_{$type}_%")
                ->latest()
                ->first();
            
            if ($wordlistRecord) {
                $filePath = "{$this->wordlistPath}/{$wordlistRecord->filename}";
                $exists = Storage::exists($filePath);
                
                $wordlists[] = [
                    'type' => $type,
                    'filename' => $wordlistRecord->filename,
                    'exists' => $exists,
                    'size' => $exists ? Storage::size($filePath) : 0,
                    'last_modified' => $exists ? Storage::lastModified($filePath) : null,
                    'created_at' => $wordlistRecord->created_at,
                    'updated_at' => $wordlistRecord->updated_at
                ];
            } else {
                // No wordlist record exists for this type
                $wordlists[] = [
                    'type' => $type,
                    'filename' => null,
                    'exists' => false,
                    'size' => 0,
                    'last_modified' => null,
                    'created_at' => null,
                    'updated_at' => null
                ];
            }
        }

        return response()->json([
            'wordlists' => $wordlists
        ]);
    }

    // Delete wordlist
    public function deleteWordlist(Request $request, $type)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validTypes = ['kcip', 'gamble'];
        if (!in_array($type, $validTypes)) {
            return response()->json(['message' => 'Invalid wordlist type'], 400);
        }

        $streamer = $user->streamer;

        try {
            // Find the wordlist record for this streamer and type
            $wordlistRecord = StreamerWordsLists::where('streamer_id', $streamer->id)
                ->where('filename', 'LIKE', "%_{$type}_%")
                ->latest()
                ->first();

            if (!$wordlistRecord) {
                return response()->json(['message' => 'Wordlist not found'], 404);
            }

            $filePath = "{$this->wordlistPath}/{$wordlistRecord->filename}";

            // Delete the file if it exists
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            // Delete the database record
            $wordlistRecord->delete();

            return response()->json([
                'message' => 'Wordlist deleted successfully',
                'type' => $type
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete wordlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
