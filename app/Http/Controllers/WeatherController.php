<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class WeatherController extends Controller
{
    protected $weatherService;

    // コンストラクタでWeatherServiceをインジェクション
    public function __construct(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // STEP 1: 表示都市の決定
        $cityFromRequest = $request->input('prefecture');
        if ($cityFromRequest) {
            $city = $cityFromRequest;
        } elseif ($user && $user->prefecture) {
            $city = $user->prefecture;
        } else {
            $city = '東京都';
        }

        // STEP 2: 全ての気象データを集約
        $sneezeRate = 'N/A';
        $sneezeReliability = 0;
        $weatherData = $this->weatherService->getCurrentWeather($city);
        $comprehensiveData = null; // 初期化

        if ($weatherData) {
            $lat = $weatherData['coord']['lat'];
            $lon = $weatherData['coord']['lon'];
            $airPollutionData = $this->weatherService->getAirPollution($lat, $lon);
            $oneCallData = $this->weatherService->getOneCallData($lat, $lon);
            $comprehensiveData = array_merge($weatherData, $airPollutionData ?? [], $oneCallData ?? []);
        }

        // STEP 3: くしゃみ確率の算出
        if ($comprehensiveData) {
            $baseSneezeRate = $this->weatherService->calculateSneezeRateFromWeather($comprehensiveData);
            if ($user && $user->getNoseType()['type'] !== '未設定タイプ') {
                $result = $this->weatherService->calculatePersonalSneezeRate($baseSneezeRate, $user, $comprehensiveData);
                $sneezeRate = $result['rate'];
                $sneezeReliability = $result['reliability'];
            } else {
                $sneezeRate = $baseSneezeRate;
                $sneezeReliability = $this->weatherService->calculateReliabilityFromWeather($comprehensiveData);
            }
        }

        // ★★★ ここから、不足していた変数の準備ロジック ★★★

        // ユーザーの鼻タイプ情報を取得
        $noseTypeInfo = ['type' => '未設定', 'icon' => '❓', 'description' => '体質情報を設定すると、あなたの鼻タイプが表示されます。'];
        if ($user) {
            $noseTypeInfo = $user->getNoseType();
        }
        $hasNoseType = ($noseTypeInfo['type'] !== '未設定' && $noseTypeInfo['type'] !== '未設定タイプ');

        // 注釈テキスト
        $sneezeRateNote = '';
        if (!$hasNoseType) {
            $sneezeRateNote = '体質情報を設定すると、さらに正確な確率が分かります。';
        }

        // シェアテキストの生成
        $shareText = "私の今日のくしゃみ確率は【{$sneezeRate}%】でした！";
        if ($hasNoseType) {
            $shareText .= " 鼻タイプは「{$noseTypeInfo['type']}」です。";
        }
        $shareText .= " #鼻ムズバスターズ";
        $appUrl = url('/');
        $twitterShareUrl = "https://twitter.com/intent/tweet?" . http_build_query([
            'text' => $shareText,
            'url' => $appUrl
        ]);

        // OGP用のデータ
        $ogpTitle = "今日のくしゃみ確率 {$sneezeRate}%";
        $ogpDescription = $shareText;

        // ★★★ ここまで ★★★

        // STEP 4: ビューに渡すデータを全てまとめる
        $viewData = [
            'user' => $user,
            'weatherData' => $weatherData,
            'selectedCity' => $city,
            'sneezeRate' => $sneezeRate,
            'sneezeReliability' => $sneezeReliability,
            'userNoseType' => $noseTypeInfo['type'],
            'userNoseTypeIcon' => $noseTypeInfo['icon'],
            'userNoseTypeDescription' => $noseTypeInfo['description'],
            'hasNoseType' => $hasNoseType,
            'sneezeRateNote' => $sneezeRateNote,
            'twitterShareUrl' => $twitterShareUrl,
            'title' => $ogpTitle,
            'description' => $ogpDescription,
            'ogImage' => asset('images/ogp-default.png'),
        ];

        // STEP 5: ビューを返す
        if ($user) {
            return view('dashboard', $viewData);
        } else {
            return view('home', $viewData);
        }
    }
}
