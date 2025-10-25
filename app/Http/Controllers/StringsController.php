<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Strings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StringsController extends Controller
{
    //analyse string
    public function analyseString(Request $request)
    {
        // Validate incoming request
        $validated = $this->validateRequest($request);

        // Check if string already exists
        if ($this->stringExists($validated['value'])) {
            return response()->json([
                'error' => 'String already exists'
            ], 409);
        }

        // Analyze string properties
        $analysis = $this->analyzeStringProperties($validated['value']);

        // Store string analysis record
        $record = $this->storeStringRecord($validated['value'], $analysis);

        // Return JSON response with 201 Created
        return $this->respondWithAnalysis($validated['value'], $analysis, $record);
    }

    private function validateRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($errors->has('value')) {
                if ($request->has('value')) {
                    throw new HttpResponseException(response()->json([
                        'error' => 'Invalid data type for "value" (must be string)'
                    ], 422));
                } else {
                    throw new HttpResponseException(response()->json([
                        'error' => 'Invalid request body or missing "value" field'
                    ], 400));
                }
            }

            throw new HttpResponseException(response()->json([
                'error' => 'Invalid request'
            ], 400));
        }

        return $validator->validated();
    }

    private function stringExists(string $value): bool
    {
        return Strings::where('value', $value)->exists();
    }

    private function analyzeStringProperties(string $value): array
    {
        $length = mb_strlen($value);

        // Normalize string for palindrome check: lowercase, remove non-letters/digits
        $normalized = preg_replace('/[\W_]+/u', '', mb_strtolower($value));
        $is_palindrome = $normalized === strrev($normalized);

        $characters = mb_str_split($value);
        $unique_characters = count(array_unique($characters));

        // Count words robustly for UTF-8 strings
        preg_match_all('/\p{L}+/u', $value, $matches);
        $word_count = count($matches[0]);

        $sha256_hash = hash('sha256', $value);

        $character_frequency_map = [];
        foreach ($characters as $char) {
            $character_frequency_map[$char] = ($character_frequency_map[$char] ?? 0) + 1;
        }

        return compact(
            'length',
            'is_palindrome',
            'unique_characters',
            'word_count',
            'sha256_hash',
            'character_frequency_map'
        );
    }

    private function storeStringRecord(string $value, array $analysis)
    {
        return Strings::create([
            'value' => $value,
            'length' => $analysis['length'],
            'is_palindrome' => $analysis['is_palindrome'],
            'unique_characters' => $analysis['unique_characters'],
            'word_count' => $analysis['word_count'],
            'sha256_hash' => $analysis['sha256_hash'],
            'character_frequency_map' => json_encode($analysis['character_frequency_map']),
        ]);
    }

    private function respondWithAnalysis(string $value, array $analysis, $record)
    {
        return response()->json([
            'id' => $analysis['sha256_hash'],
            'value' => $value,
            'properties' => [
                'length' => $analysis['length'],
                'is_palindrome' => $analysis['is_palindrome'],
                'unique_characters' => $analysis['unique_characters'],
                'word_count' => $analysis['word_count'],
                'sha256_hash' => $analysis['sha256_hash'],
                'character_frequency_map' => $analysis['character_frequency_map'],
            ],
            'created_at' => $record->created_at->toISOString(),
        ], 201);
    }



    //Check if string exist is db
    public function checkString(string $string_value)
    {
        $record = Strings::where('value', $string_value)->first();
    
        if (!$record) {
            return response()->json([
                'error' => 'String does not exist in the system'
            ], 404);
        }
    
        return response()->json([
            'id' => $record->sha256_hash,
            'value' => $record->value,
            'properties' => [
                'length' => (int) $record->length,
                'is_palindrome' => (bool) $record->is_palindrome,
                'unique_characters' => (int) $record->unique_characters,
                'word_count' => (int) $record->word_count,
                'sha256_hash' => $record->sha256_hash,
                'character_frequency_map' => json_decode($record->character_frequency_map, true),
            ],
            'created_at' => $record->created_at->toISOString(),
        ], 200);
    }
    

    
    public function filterByNaturalLanguage(Request $request)
    {
        $queryString = trim($request->query('query'));
        if (!$queryString) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        $filters = [];

        // Parse "all single word palindromic strings" → word_count=1, is_palindrome=true
        if (preg_match('/single word/i', $queryString)) {
            $filters['word_count'] = 1;
        }

        if (preg_match('/palindromic|palindrome/i', $queryString)) {
            $filters['is_palindrome'] = true;
        }

        // Parse "strings longer than X characters" → min_length=X+1
        if (preg_match('/longer than (\d+) characters/i', $queryString, $matches)) {
            $filters['min_length'] = ((int)$matches[1]) + 1;
        }

        // Parse "strings containing the letter X" → contains_character=X
        if (preg_match('/containing (?:the )?letter (\w)/i', $queryString, $matches)) {
            $filters['contains_character'] = strtolower($matches[1]);
        }

        // Parse "palindromic strings that contain the first vowel" → is_palindrome=true, contains_character=a
        if (preg_match('/palindromic strings that contain the first vowel/i', $queryString)) {
            $filters['is_palindrome'] = true;
            $filters['contains_character'] = 'a';
        }

        if (empty($filters)) {
            return response()->json(['error' => 'Unable to parse natural language query'], 400);
        }

        $query = Strings::query();

        if (isset($filters['word_count'])) {
            $query->where('word_count', $filters['word_count']);
        }

        if (isset($filters['is_palindrome'])) {
            $query->where('is_palindrome', $filters['is_palindrome']);
        }

        if (isset($filters['min_length'])) {
            $query->where('length', '>=', $filters['min_length']);
        }

        if (isset($filters['contains_character'])) {
            $char = $filters['contains_character'];
            $query->where(function ($q) use ($char) {
                $q->whereRaw("CAST(JSON_EXTRACT(character_frequency_map, '$.\"$char\"') AS UNSIGNED) > 0")
                  ->orWhereRaw("CAST(JSON_EXTRACT(character_frequency_map, '$.\"".strtoupper($char)."\"') AS UNSIGNED) > 0");
            });
        }

        $results = $query->pluck('value')->toArray();

        if (empty($results)) {
            return response()->json([
                'error' => 'Query parsed but resulted in conflicting filters',
                'parsed_filters' => $filters
            ], 422);
        }

        return response()->json([
            'data' => $results,
            'count' => count($results),
            'interpreted_query' => [
                'original' => $queryString,
                'parsed_filters' => $filters,
            ],
        ], 200);
    }




    //filter string 
    public function filterStrings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_palindrome' => 'nullable|in:true,false,1,0',
            'min_length' => 'nullable|integer|min:0',
            'max_length' => 'nullable|integer|min:0',
            'word_count' => 'nullable|integer|min:0',
            'contains_character' => 'nullable|string|size:1'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid query parameter values or types',
                'details' => $validator->errors(),
            ], 400);
        }
    
        $query = Strings::query();
    
        if ($request->filled('is_palindrome')) {
            $boolVal = filter_var($request->query('is_palindrome'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_palindrome', $boolVal);
        }
    
        if ($request->filled('min_length')) {
            $query->where('length', '>=', (int) $request->input('min_length'));
        }
    
        if ($request->filled('max_length')) {
            $query->where('length', '<=', (int) $request->input('max_length'));
        }
    
        if ($request->filled('word_count')) {
            $query->where('word_count', (int) $request->input('word_count'));
        }
    
        if ($request->filled('contains_character')) {
            $char = $request->input('contains_character');
            $query->where('value', 'LIKE', '%' . $char . '%');
        }
    
        $results = $query->get();
    
        // 🔥 FIX 1: PLACE THIS BLOCK HERE (RIGHT AFTER $results = $query->get();)
        $filteredIsPalindrome = null;
        if ($request->filled('is_palindrome')) {
            $filteredIsPalindrome = filter_var($request->query('is_palindrome'), FILTER_VALIDATE_BOOLEAN);
        }
    
        // 🔥 FIX 2: PLACE THIS BLOCK HERE (REPLACE ALL CODE BELOW)
        $response = $results->map(function ($record) {
            return [
                'id' => $record->sha256_hash,
                'value' => $record->value,
                'properties' => [
                    'length' => (int) $record->length,
                    'is_palindrome' => (bool) $record->is_palindrome,
                    'unique_characters' => (int) $record->unique_characters,
                    'word_count' => (int) $record->word_count,
                    'sha256_hash' => $record->sha256_hash,
                    'character_frequency_map' => json_decode($record->character_frequency_map, true),
                ],
                'created_at' => $record->created_at->toISOString(),
            ];
        });
    
        return response()->json([
            'data' => $response->toArray(),
            'count' => $results->count(),
            'filters_applied' => [
                'is_palindrome' => $filteredIsPalindrome,
                'min_length' => $request->input('min_length'),
                'max_length' => $request->input('max_length'),
                'word_count' => $request->input('word_count'),
                'contains_character' => $request->input('contains_character'),
            ]
        ], 200);
    }
    
        
        




    //Delete string
    public function deleteString(string $string_value)
    {
        $stringRecord = Strings::where('value', $string_value)->first();
    
        if (!$stringRecord) {
            return response()->json([
                'error' => 'String does not exist in the system'
            ], 404);
        }
    
        $stringRecord->delete();
    
        return response()->noContent();
    }




}




