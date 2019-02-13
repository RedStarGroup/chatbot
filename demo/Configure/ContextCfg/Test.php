<?php

/**
 * Class Test
 * @package Commune\Chatbot\Demo\Configure\ContextCfg
 */

namespace Commune\Chatbot\Demo\Configure\ContextCfg;

use Commune\Chatbot\Framework\Context\Context;
use Commune\Chatbot\Framework\Context\ContextCfg;
use Commune\Chatbot\Framework\Context\Predefined\Answer;
use Commune\Chatbot\Framework\Conversation\Scope;
use Commune\Chatbot\Framework\Intent\IntentData;
use Commune\Chatbot\Framework\Routing\DialogRoute;

class Test extends ContextCfg
{
    const SCOPE = [Scope::MESSAGE];

    const DEPENDS = [
        'q1' => [
            Answer::class,
            [
                'question' => 'test 依赖当前问题的答案, 请输入回答. 输入back会返回上一页',
                'default' => 'back'
            ]
        ],
    ];

    public function prepared(Context $context)
    {
        $context->info('进入test语境. 依赖回答为: '. $context['q1']['answer']);
    }

    public function routing(DialogRoute $route)
    {
        $route->prepared()
            ->redirectIf(function(Context $context) {
                $back = $context['q1']['answer'] === 'back';
                if ($back) {
                    $context->info('由于输入为back, 将会返回上一单元');
                }
                return $back;
            })->backward();

        $route->fallback()
            ->action(function(Context $context, IntentData $intent){
                $context->info('test:' .$intent->getMessage()->getText());
            }) ;

        $route->hearsRegex('hello')
            ->action(function (Context $context, IntentData $intent) {
                $context->info("hello world");
            });

        $route->hearsRegex('back')
            ->info('go backward')
            ->backward();

    }

}