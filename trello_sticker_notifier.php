<?php
require_once("./env.php");

class TrelloStampNotifier
{
    /* @var string Trello API利用時のKey */
    private string $trelloApiKey = TRELLO_API_KEY;

    /* @var string Trello API利用時のToken */
    private string $trelloApiToken = TRELLO_API_TOKEN;

    /* @var string ボードID */
    private string $trelloBoardId = TRELLO_BOARD_ID;

    /* @var string Slack通知時に使うWebhook URL */
    private string $slackWebhookUrl = SLACK_WEBHOOK_URL;

    /* @var bool trueならTrelloのボード情報などを出力するだけ */
    private bool $isDebugMode;

    /**
     * constructor
     *
     * @param boolean $isDebugMode trueならTrelloのボード情報などを出力するだけ
     */
    public function __construct(bool $isDebugMode)
    {
        $this->isDebugMode = $isDebugMode;
    }

    /**
     * main
     *
     * @return void
     */
    public function main(): void
    {
        if ($this->isDebugMode) {
            $this->debug();
            return;
        }

        $cards = $this->getCardsInBoard();
    }

    /**
     * cURLでHTTPリクエスト
     *
     * @param string  $url           対象のURL
     * @param boolean $isPostRequest POSTならtrue
     * @param array   $requestBody   POST時に使うリクエストボディ
     *
     * @return array|null 取得結果
     */
    private function curlExec(
        string $url,
        bool $isPostRequest = false,
        array $requestBody = []
    ): ?array {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: application/json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, $isPostRequest);

        if ($isPostRequest && !empty($requestBody)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        }

        $res = curl_exec($ch);
        if (!$res) {
            return null;
        }
        $result = json_decode($res, true);
        return $result;
    }

    /**
     * ボードIDを指定してカードを取得
     *
     * @return array|null ボード上のカード情報
     */
    private function getCardsInBoard(): ?array
    {
        $url = "https://api.trello.com/1/boards/{$this->trelloBoardId}/cards?key={$this->trelloApiKey}&token={$this->trelloApiToken}";
        return $this->curlExec($url);
    }

    /**
     * ボード情報などを出力する
     *
     * @return void
     */
    private function debug(): void
    {
        echo "未実装だよーん\n";
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    if ($argc > 2) {
        echo "引数の数が不正\n";
        return;
    }

    $isDebugMode = isset($argv[1]);
    (new TrelloStampNotifier($isDebugMode))->main();
}
