<?php
declare(strict_types=1);

namespace Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Opencart\System\Engine\Event;
use Opencart\System\Engine\Action;
use Opencart\System\Engine\Registry;

class EventTest extends TestCase
{
    private Event $event;
    private Registry $registry;

    protected function setUp(): void
    {
        $this->registry = new Registry();
        $this->event = new Event($this->registry);
    }

    // --- Construction ---

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Event::class, $this->event);
    }

    // --- register / trigger ---

    public function testRegisterDoesNotThrow(): void
    {
        $action = $this->createMock(Action::class);
        $this->event->register('catalog/model/test', $action);
        $this->assertTrue(true, 'register executed without error');
    }

    public function testTriggerReturnsEmptyStringWhenNoMatch(): void
    {
        $result = $this->event->trigger('catalog/model/nonexistent');
        $this->assertSame('', $result);
    }

    public function testTriggerCallsMatchingAction(): void
    {
        $action = $this->createMock(Action::class);
        $action->expects($this->once())
               ->method('execute')
               ->with($this->registry, $this->anything());

        $this->event->register('catalog/model/test', $action);
        $this->event->trigger('catalog/model/test');
    }

    public function testTriggerSupportsWildcardPattern(): void
    {
        $action = $this->createMock(Action::class);
        $action->expects($this->once())
               ->method('execute');

        $this->event->register('catalog/model/*', $action);
        $this->event->trigger('catalog/model/product/getProduct');
    }

    public function testTriggerDoesNotCallNonMatchingAction(): void
    {
        $action = $this->createMock(Action::class);
        $action->expects($this->never())
               ->method('execute');

        $this->event->register('admin/model/test', $action);
        $this->event->trigger('catalog/model/test');
    }

    // --- priority ordering ---

    public function testRegisterRespectsPriority(): void
    {
        $order = [];

        $action1 = $this->createMock(Action::class);
        $action1->method('execute')->willReturnCallback(function () use (&$order) { $order[] = 'low'; });

        $action2 = $this->createMock(Action::class);
        $action2->method('execute')->willReturnCallback(function () use (&$order) { $order[] = 'high'; });

        // Register low-priority first, high-priority second
        $this->event->register('test/event', $action1, 10);
        $this->event->register('test/event', $action2, 1);

        $this->event->trigger('test/event');

        $this->assertSame(['high', 'low'], $order);
    }

    // --- unregister ---

    public function testUnregisterRemovesMatchingAction(): void
    {
        $action = $this->createMock(Action::class);
        $action->method('getId')->willReturn('extension/module/test');
        $action->expects($this->never())->method('execute');

        $this->event->register('catalog/model/test', $action);
        $this->event->unregister('catalog/model/test', 'extension/module/test');
        $this->event->trigger('catalog/model/test');
    }

    public function testUnregisterLeavesOtherActions(): void
    {
        $action1 = $this->createMock(Action::class);
        $action1->method('getId')->willReturn('route/a');
        $action1->expects($this->never())->method('execute');

        $action2 = $this->createMock(Action::class);
        $action2->method('getId')->willReturn('route/b');
        $action2->expects($this->once())->method('execute');

        $this->event->register('test/event', $action1);
        $this->event->register('test/event', $action2);

        $this->event->unregister('test/event', 'route/a');
        $this->event->trigger('test/event');
    }

    // --- clear ---

    public function testClearRemovesAllActionsForTrigger(): void
    {
        $action = $this->createMock(Action::class);
        $action->expects($this->never())->method('execute');

        $this->event->register('catalog/model/test', $action);
        $this->event->clear('catalog/model/test');
        $this->event->trigger('catalog/model/test');
    }

    public function testClearDoesNotAffectOtherTriggers(): void
    {
        $action1 = $this->createMock(Action::class);
        $action1->expects($this->never())->method('execute');

        $action2 = $this->createMock(Action::class);
        $action2->expects($this->once())->method('execute');

        $this->event->register('trigger/a', $action1);
        $this->event->register('trigger/b', $action2);

        $this->event->clear('trigger/a');
        $this->event->trigger('trigger/a');
        $this->event->trigger('trigger/b');
    }
}
