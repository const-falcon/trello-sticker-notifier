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

    /* @var string ステッカーID */
    private string $trelloStickerId = TRELLO_STICKER_ID;

    /* @var string Slack通知時に使うWebhook URL */
    private string $slackWebhookUrl = SLACK_WEBHOOK_URL;

    // TODO: 嘘コメントどうにかする
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
        // debug mode
        if ($this->isDebugMode) {
            $this->debug();
            echo "debug modeで起動\n";
            return;
        }

        // カード一覧を取得
        $cards = $this->getCardsInBoard();
        if (!isset($cards)) {
            echo "カードがないよ\n";
            return;
        }

        // カードIDを抜き出す
        $cardIds = $this->extractCardIds($cards);
        // 対象のステッカーが貼られたカードの最新コメントを取得
        $latestComments = $this->getLatestCommentsForStickerCard($cardIds);
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
     * カード一覧を取得
     *
     * @return array|null ボード上のカード一覧
     */
    private function getCardsInBoard(): ?array
    {
        $url = "https://api.trello.com/1/boards/{$this->trelloBoardId}/cards?key={$this->trelloApiKey}&token={$this->trelloApiToken}";
        return $this->curlExec($url);
    }

    /**
     * カードIDを抜き出す
     *
     * @param array $cards カード情報
     *
     * @return array カードID
     */
    private function extractCardIds(array $cards): array
    {
        return array_column($cards, "id");
    }

    /**
     * 対象のステッカーが貼られたカードの最新コメントを取得
     *
     * @param array $cardIds カードID
     *
     * @return array $latestComments keyがカードID、valueが最新コメント
     */
    private function getLatestCommentsForStickerCard(array $cardIds): array
    {
        $latestComments = [];
        foreach ($cardIds as $cardId) {
            $stickerIds = $this->getStickerIds($cardId);
            // ステッカーがない || 対象のステッカーがない
            if (empty($stickerIds) || in_array($this->trelloStickerId, $stickerIds, true)) {
                continue;
            }

            $latestComment = $this->getLatestComment($cardId);
            // コメントがない
            if (!isset($latestComment)) {
                continue;
            }
            $latestComments[$cardId] = $latestComment;
        }
        return $latestComments;
    }

    /**
     * カードに貼られたステッカーIDを取得
     *
     * @param string $cardId カードID
     *
     * @return array|null ステッカーID
     */
    private function getStickerIds(string $cardId): array
    {
        $url = "https://api.trello.com/1/cards/{$cardId}/stickers?key={$this->trelloApiKey}&token={$this->trelloApiToken}";
        $stickers = $this->curlExec($url);
        return array_column($stickers, "id");
    }

    /**
     * 最新コメントを取得
     *
     * @param string $cardId カードID
     *
     * @return string|null $latestComment 最新コメント
     */
    private function getLatestComment(string $cardId): ?string
    {
        // コメントはアクションの一種、らしい
        // https://stackoverflow.com/questions/10242393/trello-api-get-card-comments
        $url = "https://api.trello.com/1/cards/{$cardId}/actions?key={$this->trelloApiKey}&token={$this->trelloApiToken}";
        $actions = $this->curlExec($url);

        $latestComment = null;
        foreach ($actions as $a) {
            if (!isset($a['data']['text'])) {
                continue;
            }
            // 一番上にあるものが最新
            $latestComment = $a['data']['text'];
            break;
        }
        return $latestComment;
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
