<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ranking;
use App\Models\SneezeLog;
use Carbon\Carbon;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RankingController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::now('Asia/Tokyo')->toDateString();
        $currentTab = $request->query('tab', 'national');

        // --- 全国ランキング関連のデータを取得 ---

        // Heroku Scheduler用
        // $nationalRankings = Ranking::where('type', 'sneeze_count')
            // ->where('ranking_date', $today)
            // ->orderBy('rank', 'asc')
            // ->get();

        // 'national_rankings_' . $today という名前で1時間保存
        $nationalRankings = Cache::remember('national_rankings_' . $today, 3600, function () use ($today) {
            // 1. まず現在の SneezeLog から都道府県別に合計を集計する（最新状態の計算）
            $summary = SneezeLog::whereDate('sneeze_logs.created_at', $today)
                ->join('users', 'sneeze_logs.user_id', '=', 'users.id')
                ->select('users.prefecture', DB::raw('SUM(sneeze_logs.count) as total'))
                ->groupBy('users.prefecture')
                ->get();

            // 2. 集計結果を Ranking テーブルに保存・更新する
            // (Rankingテーブルにデータがあれば更新、なければ作成)
            foreach ($summary as $data) {
                if (!$data->prefecture) continue; // 都道府県未設定はスキップ

                Ranking::updateOrCreate(
                    [
                        'type' => 'sneeze_count',
                        'ranking_date' => $today,
                        'prefecture' => $data->prefecture
                    ],
                    [
                        'total_count' => $data->total,
                        // 順位(rank)は後で一括でつけるので一旦0など
                        'rank' => 0 
                    ]
                );
            }

            // 3. 最後に順位(rank)を振り直す
            $allRanked = Ranking::where('type', 'sneeze_count')
                ->where('ranking_date', $today)
                ->orderBy('count', 'desc')
                ->get();

            foreach ($allRanked as $index => $r) {
                $r->update(['rank' => $index + 1]);
            }

            // 4. 最新の状態になった Ranking テーブルのデータを返す
            return Ranking::where('type', 'sneeze_count')
                ->where('ranking_date', $today)
                ->orderBy('rank', 'asc')
                ->get();
        });

        $worstSneezePrefecture = $nationalRankings->first(); // 1位のデータを取得

        // --- 個人ランキング関連（小林担当部分・修正版） ---
        
        // 1. 今日の個人ランキングTOP10を取得（くしゃみ回数の合計順）
        $personalRankings = SneezeLog::whereDate('created_at', $today)
            ->select('user_id', DB::raw('SUM(count) as total_count'), DB::raw('AVG(level) as avg_level'))
            ->groupBy('user_id')
            // MySQLの場合
            // ->orderBy('total_count', 'desc') // 回数が多い順
            ->orderByRaw('SUM(count) DESC') // PostgreSQLでは計算式を直接指定
            ->with('user') // ユーザー名を表示するためにリレーションをロード
            ->take(10) // 上位10名を表示
            ->get();

        // 2. ログインユーザー自身の順位と成績を計算
        $myRanking = null;
        if (Auth::check()) {
            $user = Auth::user();
            
            // 自分の今日の合計と平均を取得
            $myStats = SneezeLog::where('user_id', $user->id)
                ->whereDate('created_at', $today)
                ->selectRaw('SUM(count) as total_count, AVG(level) as avg_level')
                ->first();

            // データがある場合のみ順位を計算
            if ($myStats && $myStats->total_count > 0) {
                // 自分よりくしゃみ回数が多いユーザーの人数を数える（+1が自分の順位）
                // ※サブクエリを使って「合計回数が自分より多いユーザー数」を取得
                $rank = SneezeLog::whereDate('created_at', $today)
                    ->groupBy('user_id')
                    ->selectRaw('SUM(count) as total_count')
                    // MySQLの場合
                    // ->having('total_count', '>', $myStats->total_count)
                    ->havingRaw('SUM(count) > ?', [$myStats->total_count])
                    ->get()
                    ->count() + 1;

                $myRanking = [
                    'rank' => $rank,
                    'name' => $user->name,
                    'sneeze_count' => $myStats->total_count,
                    'avg_level' => round($myStats->avg_level, 1), // 小数点第1位まで
                ];
            }
        }

        // ビューに渡す変数を追加 ($myRanking)
        return view('ranking.index', compact(
            'currentTab',
            'nationalRankings',
            'worstSneezePrefecture',
            'personalRankings', // TOP10リスト
            'myRanking'         // 自分の順位データ
        ));
    }
}
