<?php

/**
 * This file is part of CommuneChatbot.
 *
 * @link     https://github.com/thirdgerb/chatbot
 * @document https://github.com/thirdgerb/chatbot/blob/master/README.md
 * @contact  <thirdgerb@gmail.com>
 * @license  https://github.com/thirdgerb/chatbot/blob/master/LICENSE
 */

namespace Commune\Ghost\IOperate;

use Commune\Blueprint\Ghost\Dialog;
use Commune\Blueprint\Ghost\Operate\Operator;
use Commune\Blueprint\Exceptions\CommuneLogicException;
use Commune\Framework\Spy\SpyAgency;


/**
 * @author thirdgerb <thirdgerb@gmail.com>
 */
abstract class AbsOperator implements Operator
{
    /**
     * @var Dialog
     */
    protected $dialog;

    /**
     * @var bool
     */
    protected $ticking = false;

    /**
     * @var bool
     */
    protected $ticked = false;

    /**
     * AbsOperator constructor.
     * @param Dialog $dialog
     */
    public function __construct(Dialog $dialog)
    {
        $this->dialog = $dialog;
        SpyAgency::incr(static::class);
    }

    /**
     * @return Operator
     */
    abstract protected function toNext() : Operator;

    /**
     * @return Operator
     */
    public function tick(): Operator
    {
        // 每个 Dialog 实例只能 tick 一次.
        if ($this->ticked) {
            throw new CommuneLogicException(
                __METHOD__
                . ' try to tick dialog that ticked'
            );
        }

        if ($this->ticking) {
            throw new CommuneLogicException(
                __METHOD__
                . ' try to tick dialog that ticking'
            );
        }

        $this->ticking = true;

        // 尝试拦截.
        $next = $this->toNext();

        $this->destroy();
        return $next;
    }

    protected function destroy() : void
    {
        $this->dialog = null;
        $this->ticking = false;
        $this->ticked = true;
    }

    public function getDialog(): Dialog
    {
        return $this->dialog;
    }

    public function getName(): string
    {
        return static::class;
    }

    public function __invoke(): Operator
    {
        return $this;
    }

    public function __destruct()
    {
        $this->dialog = null;
        SpyAgency::incr(static::class);
    }
}