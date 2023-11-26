<?php
require_once("./env.php");

class TrelloStampNotifier
{
    /* @var string Trello API利用時のKey */
    private string $trelloApiKey = TRELLO_API_KEY;

    /* @var string Trello API利用時のToken */
    private string $trelloApiToken = TRELLO_API_TOKEN;

    /* @var string Slack通知時に使うWebhook URL */
    private string $slackWebhookUrl = SLACK_WEBHOOK_URL;

    /* @var bool trueならTrelloのボード情報などを出力するだけ */
    private bool $isDebugMode;

    public function __construct(bool $isDebugMode)
    {
        $this->isDebugMode = $isDebugMode;
    }

    public function main(): void
    {
        if ($this->isDebugMode) {
            $this->debug();
        }
    }

    private function debug()
    {
        // debug...
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
