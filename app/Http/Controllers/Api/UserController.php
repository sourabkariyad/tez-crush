<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserScore;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validated = $this->validateRegistration($request);

        $user = $this->createOrUpdateUser($validated);

        if (!empty($validated['level']) && !empty($validated['score'])) {
            $this->updateUserScore($user, $validated['level'], $validated['score']);
        }

        $leaderboard = $this->getLeaderboardWithUser($user->id, 10);

        $user->load('scores', 'referrals', 'referredBy');

        return response()->json([
            'message' => $user->wasRecentlyCreated
                ? 'User registered successfully'
                : 'User already exists',
            'user' => [
                'details' => $user->only(['id', 'wallet_address', 'name', 'user_unique_id']),
                'scores' => $user->scores()->paginate(20),
                'referrals' => $user->referrals, 
                'referred_by' => $user->referredBy,  
            ],
            'leaderboard' => $leaderboard,
        ], $user->wasRecentlyCreated ? 201 : 200);
    }

    protected function validateRegistration(Request $request)
    {
        return $request->validate([
            'wallet_address' => 'required|string|max:255',
            'name'           => 'nullable|string|max:255',
            'level'          => 'nullable|integer|min:1',
            'score'          => 'nullable|integer',
            'referred_user_id' => 'nullable',
        ]);
    }

    protected function createOrUpdateUser(array $data)
    {
        $referredUserId = null;
        if (!empty($data['referred_user_id'])) {
            $refUser = User::where('user_unique_id', $data['referred_user_id'])->first();
            if ($refUser) {
                $referredUserId = $refUser->id;
            }
        }

        return User::firstOrCreate(
            ['wallet_address' => $data['wallet_address']],
            ['referred_user_id' => $referredUserId],
            ['name' => $data['name'] ?? null]
        );
    }

    protected function updateUserScore(User $user, int $level, int $score)
    {
        if ($level > 1) {
            $previousLevelExists = UserScore::where('user_id', $user->id)
                ->where('level', $level - 1)
                ->exists();

            if (!$previousLevelExists) {
                abort(400, "Level " . ($level - 1) . " must be completed before starting Level {$level}");
            }
        }

        UserScore::updateOrCreate(
            ['user_id' => $user->id, 'level' => $level],
            ['score' => $score]
        );
    }

    protected function getLeaderboardWithUser(int $userId, int $limit = 10)
    {
        $leaderboardQuery = DB::table(DB::raw('(
            SELECT 
                users.id,
                users.name,
                users.wallet_address,
                MAX(user_scores.level) AS highest_level,
                MAX(user_scores.score) AS highest_score,
                RANK() OVER (ORDER BY MAX(user_scores.level) DESC, MAX(user_scores.score) DESC) AS user_rank
            FROM users
            JOIN user_scores ON users.id = user_scores.user_id
            GROUP BY users.id, users.name, users.wallet_address
        ) as ranked'))
        ->orderBy('user_rank');

        $leaderboard = $leaderboardQuery->limit($limit)->get();
        $userEntry = $leaderboardQuery->where('id', $userId)->first();

        if (!$leaderboard->contains('id', $userId) && $userEntry) {
            $leaderboard->push($userEntry);
        }

        return $leaderboard;
    }

    public function leaderboard(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'limit'   => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $validated['limit'] ?? 10;
        $userId = $validated['user_id'] ?? null;

        if ($userId) {
            $leaderboard = $this->getLeaderboardWithUser($userId, $limit);
        } else {
            $leaderboard = DB::table(DB::raw('(
                SELECT 
                    users.id,
                    users.name,
                    users.wallet_address,
                    MAX(user_scores.level) AS highest_level,
                    MAX(user_scores.score) AS highest_score,
                    RANK() OVER (ORDER BY MAX(user_scores.level) DESC, MAX(user_scores.score) DESC) AS user_rank
                FROM users
                JOIN user_scores ON users.id = user_scores.user_id
                GROUP BY users.id, users.name, users.wallet_address
            ) as ranked'))
            ->orderBy('user_rank')
            ->limit($limit)
            ->get();
        }

        return response()->json([
            'leaderboard' => $leaderboard
        ]);
    }

}

