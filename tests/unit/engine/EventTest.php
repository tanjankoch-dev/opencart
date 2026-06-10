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

    protected function setUp(): void
    {
        $this->registry = new Registry();
    }

    private function buildEvent(): Event
    {
        return new Event($this->registry);
    }

    public function testTriggerWithNoRegisteredEventsReturnsEmpty(): void
    {
        $event = $this->buildEvent();
        $this->assertSame('', $event->trigger('catalog/view'));
    }

    public function testRegisterAndTriggerInvokesCallback(): void
    {
        $event = $this->buildEvent();

        $action = $this->createMock(Action::class);
        $action->expects($this->once())->method('execute');

        $event->register('catalog/view', $action);
        $event->trigger('catalog/view');
    }

    public function testTriggerDoesNotInvokeNonMatchingEvents(): void
    {
        $event = $this->buildEvent();

        $action = $this->createMock(Action::class);
        $action->expects($this->never())->method('execute');

        $event->register('catalog/view', $action);
        $event->trigger('admin/view');
    }

    public function testWildcardTriggerMatches(): void
    {
        $event = $this->buildEvent();

        $action = $this->createMock(Action::class);
        $action->expects($this->once())->method('execute');

        $event->register('catalog/*', $action);
        $event->trigger('catalog/view');
    }

    public function testUnregisterRemovesEvent(): void
    {
        $event = $this->buildEvent();

        $action = $this->createMock(Action::class);
        $action->method('getId')->willReturn('test/route');
        $action->expects($this->never())->method('execute');

        $event->register('catalog/view', $action);
        $event->unregister('catalog/view', 'test/route');
        $event->trigger('catalog/view');
    }

    public function testClearRemovesAllForTrigger(): void
    {
        $event = $this->buildEvent();

        $action1 = $this->createMock(Action::class);
        $action1->expects($this->never())->method('execute');

        $action2 = $this->createMock(Action::class);
        $action2->expects($this->never())->method('execute');

        $event->register('catalog/view', $action1);
        $event->register('catalog/view', $action2);
        $event->clear('catalog/view');
        $event->trigger('catalog/view');
    }

    public function testPriorityOrdersExecution(): void
    {
        $event = $this->buildEvent();
        $order = [];

        $action1 = $this->createMock(Action::class);
        $action1->method('execute')->willReturnCallback(function () use (&$order) {
            $order[] = 'second';
        });

        $action2 = $this->createMock(Action::class);
        $action2->method('execute')->willReturnCallback(function () use (&$order) {
            $order[] = 'first';
        });

        $event->register('catalog/view', $action1, 10);
        $event->register('catalog/view', $action2, 1);
        $event->trigger('catalog/view');

        $this->assertSame(['first', 'second'], $order);
    }
}
