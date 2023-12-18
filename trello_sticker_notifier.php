<?php
require_once("./env.php");

class TrelloStickerNotifier
{
    /* @var string Trello API利用時のKey */
    private string $trelloApiKey = TRELLO_API_KEY;

    /* @var string Trello API利用時のToken */
    private string $trelloApiToken = TRELLO_API_TOKEN;

    /* @var string ボードID */
    private string $trelloBoardId = TRELLO_BOARD_ID;

    /* @var string ステッカーID */
    private string $trelloStickerImage = TRELLO_STICKER_IMAGE;

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
        // Trello情報を整形
        $formattedTrelloInfo = $this->formatTrelloInfo($cards, $latestComments);
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
            $stickerImages = $this->getStickerImages($cardId);
            if (empty($stickerImages) || !in_array($this->trelloStickerImage, $stickerImages, true)) {
                continue;
            }
            $latestComments[$cardId] = $this->getLatestComment($cardId);
        }
        return $latestComments;
    }

    /**
     * カードに貼られたステッカーIDを取得
     *
     * @param string $cardId カードID
     *
     * @return array|null ステッカー画像
     */
    private function getStickerImages(string $cardId): array
    {
        $url = "https://api.trello.com/1/cards/{$cardId}/stickers?key={$this->trelloApiKey}&token={$this->trelloApiToken}";
        $stickers = $this->curlExec($url);
        return array_column($stickers, "image");
    }

    /**
     * 最新コメントを取得
     *
     * @param string $cardId カードID
     *
     * @return string $latestComment 最新コメント
     */
    private function getLatestComment(string $cardId): string
    {
        // コメントはアクションの一種、らしい
        // https://stackoverflow.com/questions/10242393/trello-api-get-card-comments
        $url = "https://api.trello.com/1/cards/{$cardId}/actions?key={$this->trelloApiKey}&token={$this->trelloApiToken}";
        $actions = $this->curlExec($url);

        $latestComment = "";
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
     * Trello情報を整形
     *
     * @param array $cards          カード一覧
     * @param array $latestComments 最新コメント
     *
     * @return array $result 整形したTrello情報
     */
    private function formatTrelloInfo(array $cards, array $latestComments): array
    {
        $result = [];
        foreach ($cards as $card) {
            $cardId = $card['id'];
            if (!isset($latestComments[$cardId])) {
                continue;
            }

            // TODO: 今は要らないが、担当者も欲しい気がする
            $result[$cardId] = [
                "name"           => $card['name'],
                "short_url"      => $card['shortUrl'],
                "latest_comment" => $latestComments[$cardId],
            ];
        }
        return $result;
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
    (new TrelloStickerNotifier($isDebugMode))->main();
}
