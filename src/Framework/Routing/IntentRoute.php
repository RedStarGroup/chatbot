<?php

/**
 * Class IntentRoute
 * @package Commune\Chatbot\Host\Routing
 */

namespace Commune\Chatbot\Framework\Routing;


use Commune\Chatbot\Framework\Context\Predefined\Answer;
use Commune\Chatbot\Framework\Directing\Location;
use Commune\Chatbot\Framework\Exceptions\ConfigureException;
use Commune\Chatbot\Framework\Support\Pipeline;
use Commune\Chatbot\Framework\Context\Context;
use Commune\Chatbot\Framework\Conversation\Conversation;
use Commune\Chatbot\Framework\Directing\Director;
use Commune\Chatbot\Framework\Intent\Intent;
use Commune\Chatbot\Contracts\ChatbotApp;
use Commune\Chatbot\Framework\Intent\IntentFactory;
use Commune\Chatbot\Framework\Message\Message;

class IntentRoute
{
    /**
     * @var ChatbotApp
     */
    protected $app;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var IntentFactory
     */
    protected $matcher;

    protected $pipeline;

    /*----- 运算属性 -----*/

    protected $availableCondition;

    protected $actionConditions = [];

    protected $redirectConditions = [];

    protected $actions = [];

    protected $redirects = [];

    protected $middleware = [];

    public function __construct(
        ChatbotApp $app,
        Router $router,
        string $id = null,
        IntentFactory $matcher = null
    )
    {
        $this->id = $id ?? static::class ;
        $this->app = $app;
        $this->router = $router;
        $this->matcher = $matcher ? : new IntentFactory();
    }

    /**
     * @return string
     */
    public function getListenIntent(): ? string
    {
        return $this->matcher->getIntentName();
    }


    public function getIntentFactory() : IntentFactory
    {
        return $this->matcher;
    }

    public function getId() : string
    {
        return $this->id;
    }

    /*------- 更多match -------*/


    public function hearsCommand(string $signature) : self
    {
        $this->matcher->addCommand($signature);
        return $this;
    }

    public function hearsRegex(array $regex) : self
    {
        $this->matcher->addRegex($regex);
        return $this;
    }

    public function hearsMatcher(callable $matcher) : self
    {
        $this->matcher->addMatcher($matcher);
        return $this;
    }


    /*------- 重定向 -------*/

    public function middleware(string ...$middleware) : self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /*------- 定义逻辑 -------*/


    /**
     * @param string|array|callable $condition
     * @return callable
     */
    protected function warpCondition($condition) : callable
    {
        if (is_callable($condition)) {
            return $condition;
        } elseif (is_string($condition)) {
            return function(Context $context) use ($condition) {
                return boolval($context[$condition]);
            };
        } elseif (is_array($condition)) {
            return function(Context $context) use ($condition) {
                foreach($condition as $key => $val) {
                    if ($context->fetch($key) != $val) {
                        return false;
                    }
                }

                return true;
            };
        } else {
            //todo
            throw new ConfigureException();
        }
    }

    /**
     * @param string|array|callable $condition
     * @return self
     */
    public function availableWhen($condition) : self
    {
        $this->availableCondition = $this->warpCondition($condition);
        return $this;
    }

    public function actingWhile($condition) : self
    {
        $this->actionConditions[] = $this->warpCondition($condition);
        return $this;
    }

    /**
     * @param string|array|callable $condition
     * @return self
     */
    public function redirectIf($condition) : self
    {
        $index = $this->maxIndexOf($this->actions);
        $this->redirectConditions[$index][] = $this->warpCondition($condition);
        return $this;
    }

    protected function pushAction(\Closure $action)
    {
        $index = $this->maxIndexOf($this->actionConditions);
        if (!isset($this->actionConditions[$index])) {
            $this->actionConditions[$index] = null;
        }
        if (!isset($this->actions[$index])) {
            $this->actions[$index] = [];
        }
        $this->actions[$index][] = $action;
        $this->redirectConditions[$index] = [];
    }

    public function setRedirect(\Closure $redirect)
    {
        $actionIndex = $this->maxIndexOf($this->actions);
        $redirectIndex = $this->maxIndexOf($this->redirectConditions[$actionIndex]);
        $this->redirects[$actionIndex][$redirectIndex] = $redirect;
    }


    /*------- actions -------*/

    public function controller(string $controllerName, string $method) : self
    {
        $this->pushAction(function(Context $context, Intent $intent) use ($controllerName, $method) {
            $controller = $this->app->make($controllerName);
            return $controller->{$method}($context, $intent);
        });
        return $this;
    }

    public function action(callable $action) : self
    {
        $this->pushAction($action);
        return $this;
    }

    //todo
    public function callSelfMethod(string $method) : self
    {
        $this->pushAction(function(Context $context, Intent $intent) use ($method){
            $context->callConfigMethod($method, $intent);
        });
        return $this;
    }

    public function reply(Message $message)
    {
        $action = function(Context $context) use ($message){
            $context->reply($message);
        };
        $this->pushAction($action);
        return $this;
    }


    public function info(string $message, string $verbose = Message::NORMAL)
    {
        $this->pushAction(function(Context $context) use ($message, $verbose){
            $context->info($message, $verbose);
        });
        return $this;
    }


    public function warn(string $message, string $verbose = Message::NORMAL)
    {
        $this->pushAction(function(Context $context, Intent $intent) use ($message, $verbose){
            $context->warn($message, $verbose);
        });
        return $this;
    }

    public function error(string $message, string $verbose = Message::NORMAL)
    {
        $this->pushAction(function(Context $context, Intent $intent) use ($message, $verbose){
            $context->error($message, $verbose);
        });
        return $this;
    }

    protected function maxIndexOf(array $array) : int
    {
        $index = count($array);
        return $index > 0 ? $index - 1 : $index;
    }


    /*------- 重定向 -------*/

    public function to(string $contextName, array $props = []) : self
    {
        $this->setRedirect(function(Director $director, Context $context) use ($contextName, $props){
            $location = $director->makeLocation($contextName, $props);
            return $director->to($location);
        });
        return $this;
    }



    public function intended() : self
    {
        $this->setRedirect(function(Director $director) {
            return $director->intended();
        });
        return $this;
    }

    public function guest(string $contextName, array $props = [], string $callback = null) : self
    {
        $this->setRedirect(function(Director $director) use ($contextName, $props, $callback){
            $location = $director->makeLocation($contextName, $props);
            return $director->guest($location, null, $callback);
        });
        return $this;
    }

    public function home() : self
    {
        $this->setRedirect(function(Director $director){
            return $director->home();
        });
        return $this;
    }

    public function forward() : self
    {
        $this->setRedirect(function(Director $director){
            return $director->forward();
        });
        return $this;
    }

    public function backward() : self
    {
        $this->setRedirect(function(Director $director){
            return $director->backward();
        });
        return $this;
    }

    public function then(callable $factory) : self
    {
        $this->setRedirect(function(Director $director, Context $context, Intent $intent) use ($factory) {
            $location = $factory($context, $intent);
            return $director->to($location);
        });
        return $this;
    }

    public function ask(string $callbackRoute, string $question, string $default = null, array $fields)
    {
        $this->setRedirect(function (
            Director $director,
            Context $context
        ) use ($callbackRoute, $question, $default, $fields){
            if (!empty($fields)) {
                $question = $context->format($question, $fields);
            }
            $location = $context->ask($callbackRoute, $question, $default);
            return $director->to($location);
        });
        return $this;
    }

    public function confirm(string $callbackRoute, string $question, string $default = null, array $fields)
    {
        $this->setRedirect(function (
            Director $director,
            Context $context
        ) use ($callbackRoute, $question, $default, $fields){
            if (!empty($fields)) {
                $question = $context->format($question, $fields);
            }
            $location = $context->confirm($callbackRoute, $question, $default);
            return $director->to($location);
        });
        return $this;
    }


    public function choose(string $callbackRoute, string $question, array $choices, int $default = 0, array $fields)
    {
        $this->setRedirect(function (
            Director $director,
            Context $context
        ) use ($callbackRoute, $question, $choices, $default, $fields){
            if (!empty($fields)) {
                $question = $context->format($question, $fields);
            }
            $location = $context->choose($callbackRoute, $question, $choices, $default);
            return $director->to($location);
        });
        return $this;
    }





    /*------- 运行逻辑 -------*/

    public function isAvailable(Context $context) :bool
    {
        if (empty($this->availableCondition)) {
            return true;
        }

        return call_user_func($this->availableCondition, $context);
    }

    public function run(
        Context $context,
        Conversation $conversation,
        Director $director
    ) : Conversation
    {
        if (empty($this->middleware)) {
            return $this->doRun($context, $conversation, $director);
        }


        $pipeline = new Pipeline($this->app, $this->middleware);
        $pipeline->setUpPipe(function(Conversation $passable) use ($context, $director) {
            return $this->doRun($context, $passable, $director);
        });
        return $pipeline->send($conversation);
    }

    protected function doRun(
        Context $context,
        Conversation $conversation,
        Director $director
    ) : Conversation
    {
        // 条件检查.
        $actionIndex = $this->indexOfRightCondition($this->actionConditions, $context);

        // action
        if (!isset($actionIndex) || !isset($this->actions[$actionIndex])) {
            return $conversation;
        }

        // 意图获取.
        $intent = $conversation->getMatchedIntent();
        foreach ($this->actions[$actionIndex] as $action) {
            $location = call_user_func($action,$context, $intent);
            //是否有上下文的重定向.
            if (isset($location) && $location instanceof  Location) {
                return $director->to($location);
            }
        }

        // redirectCondition

        if (!isset($this->redirectConditions[$actionIndex])) {
            return $conversation;
        }

        $redirectConditions = $this->redirectConditions[$actionIndex];

        $redirectIndex = $this->indexOfRightCondition($redirectConditions, $context);

        // 重定向
        if (!isset($this->redirects[$actionIndex][$redirectIndex])) {
            return $conversation;
        }

        $redirect = $this->redirects[$actionIndex][$redirectIndex];
        return call_user_func($redirect, $director, $context, $intent);

    }

    protected function indexOfRightCondition(array $conditions, Context $context) : ? int
    {
        $index = 0;
        if (empty($conditions)) {
            return $index;
        }

        foreach($conditions as $index => $condition) {
            /**
             * 为null 表示无条件为真.
             * 否则为callable
             *
             * @var callable $condition
             */
            if (is_null($condition) || $condition($context)) {
                return $index;
            }
        }

        return null;
    }

}