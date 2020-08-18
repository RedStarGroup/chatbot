<?php

/**
 * This file is part of CommuneChatbot.
 *
 * @link     https://github.com/thirdgerb/chatbot
 * @document https://github.com/thirdgerb/chatbot/blob/master/README.md
 * @contact  <thirdgerb@gmail.com>
 * @license  https://github.com/thirdgerb/chatbot/blob/master/LICENSE
 */

namespace Commune\Blueprint\NLU;

use Commune\Support\Option\AbsOption;


/**
 * @author thirdgerb <thirdgerb@gmail.com>
 *
 * @property-read string $id,
 * @property-read string $desc,
 * @property-read string $serviceInterface,
 * @property-read string $serviceAbstract,
 * @property-read string[] $listening
 *
 * @property-read string|null $managerUcl       用对话管理该服务的对话地址.
 */
class NLUServiceOption extends AbsOption
{
    const IDENTITY = 'id';

    public static function stub(): array
    {
        return [
            'id' => '',
            'desc' => '',
            'serviceInterface' => NLUService::class,
            'serviceAbstract' => '',
            'managerUcl' => null,
            'listening' => [],
        ];
    }

    public static function relations(): array
    {
        return [];
    }


}