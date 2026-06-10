<?php
declare(strict_types=1);

namespace Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Opencart\System\Engine\Event;
use Opencart\System\Engine\Action;
use Opencart\System\Engine\Registry;

class EventTest extends TestCase
{
    private Registry $registry;
    private Event $event;

    protected function setUp(): void
    {
        $this->registry = new Registry();
        $this->event = new Event($this->registry);
    }

    public function testTriggerWithNoRegisteredEventsReturnsEmptyString(): void
    {
        $result = $this->event->trigger('some/event');
        $this->assertSame('', $result);
    }

    public function testRegisterAndTriggerExactMatch(): void
    {
        $called = false;

        $action = $this->createMock(Action::class);
        $action->expects($this->once())
            ->method('execute')
            ->with($this->registry, $this->anything())
            ->willReturnCallback(function () use (&$called) {
                $called = true;
            });

        $this->event->register('catalog/view/product', $action);
        $this->event->trigger('catalog/view/product');

        $this->assertTrue($called);
    }

    public function testTriggerDoesNotFireNonMatchingEvent(): void
    {
        $action = $this->createMock(Action::class);
        $action->expects($this->never())->method('execute');

        $this->event->register('catalog/view/product', $action);
        $this->event->trigger('admin/view/dashboard');
    }

    public function testTriggerWildcardMatchesMultipleRoutes(): void
    {
        $count = 0;

        $action = $this->createMock(Action::class);
        $action->method('execute')
            ->willReturnCallback(function () use (&$count) {
                $count++;
            });

        $this->event->register('catalog/view/*', $action);

        $this->event->trigger('catalog/view/product');
        $this->event->trigger('catalog/view/category');
        $this->event->trigger('admin/view/product');

        $this->assertSame(2, $count);
    }

    public function testRegisterSortsByPriority(): void
    {
        $order = [];

        $actionLow = $this->createMock(Action::class);
        $actionLow->method('execute')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'low';
            });

        $actionHigh = $this->createMock(Action::class);
        $actionHigh->method('execute')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'high';
            });

        // Register high priority (10) first, then low (1)
        $this->event->register('test/event', $actionHigh, 10);
        $this->event->register('test/event', $actionLow, 1);

        $this->event->trigger('test/event');

        $this->assertSame(['low', 'high'], $order);
    }

    public function testUnregisterRemovesSpecificAction(): void
    {
        $action = $this->createMock(Action::class);
        $action->method('getId')->willReturn('extension/module/featured');
        $action->expects($this->never())->method('execute');

        $this->event->register('catalog/view/product', $action);
        $this->event->unregister('catalog/view/product', 'extension/module/featured');

        $this->event->trigger('catalog/view/product');
    }

    public function testUnregisterDoesNotAffectOtherActions(): void
    {
        $called = false;

        $actionKeep = $this->createMock(Action::class);
        $actionKeep->method('getId')->willReturn('keep/route');
        $actionKeep->method('execute')
            ->willReturnCallback(function () use (&$called) {
                $called = true;
            });

        $actionRemove = $this->createMock(Action::class);
        $actionRemove->method('getId')->willReturn('remove/route');
        $actionRemove->expects($this->never())->method('execute');

        $this->event->register('test/event', $actionKeep);
        $this->event->register('test/event', $actionRemove);

        $this->event->unregister('test/event', 'remove/route');
        $this->event->trigger('test/event');

        $this->assertTrue($called);
    }

    public function testClearRemovesAllActionsForTrigger(): void
    {
        $action1 = $this->createMock(Action::class);
        $action1->expects($this->never())->method('execute');

        $action2 = $this->createMock(Action::class);
        $action2->expects($this->never())->method('execute');

        $this->event->register('test/trigger', $action1);
        $this->event->register('test/trigger', $action2);

        $this->event->clear('test/trigger');
        $this->event->trigger('test/trigger');
    }

    public function testClearDoesNotAffectOtherTriggers(): void
    {
        $called = false;

        $actionKeep = $this->createMock(Action::class);
        $actionKeep->method('execute')
            ->willReturnCallback(function () use (&$called) {
                $called = true;
            });

        $actionClear = $this->createMock(Action::class);
        $actionClear->expects($this->never())->method('execute');

        $this->event->register('keep/trigger', $actionKeep);
        $this->event->register('clear/trigger', $actionClear);

        $this->event->clear('clear/trigger');
        $this->event->trigger('keep/trigger');

        $this->assertTrue($called);
    }

    public function testTriggerWithQuestionMarkWildcard(): void
    {
        $called = false;

        $action = $this->createMock(Action::class);
        $action->method('execute')
            ->willReturnCallback(function () use (&$called) {
                $called = true;
            });

        $this->event->register('catalog/view/produc?', $action);
        $this->event->trigger('catalog/view/product');

        $this->assertTrue($called);
    }

    public function testTriggerPassesArguments(): void
    {
        $receivedArgs = null;

        $action = $this->createMock(Action::class);
        $action->method('execute')
            ->willReturnCallback(function ($registry, &$args) use (&$receivedArgs) {
                $receivedArgs = $args;
            });

        $this->event->register('test/event', $action);
        $this->event->trigger('test/event', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $receivedArgs);
    }
}
