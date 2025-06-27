<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\PendingSettingsTrait;

class DummyPendingHost {
	use PendingSettingsTrait;

	public array $pending = array();

	public function set( string $key, $value ): self {
		$this->pending[ $key ] = $value;
		return $this;
	}
}

class PendingSettingsTraitTest extends TestCase {
	private DummyPendingHost $host;

	protected function setUp(): void {
		$this->host = new DummyPendingHost();
	}

	public function test_remove_marks_key_for_removal_and_has_pending(): void {
		$this->host->set( 'foo', 'bar' )->remove( 'foo' );
		$pending = $this->host->get_pending();
		$this->assertArrayHasKey( 'foo', $pending );
		$this->assertNull( $pending['foo'] );
		$this->assertTrue( $this->host->has_pending() );
	}

	public function test_clear_pending_resets_state(): void {
		$this->host->set( 'foo', 'bar' )->remove( 'foo' );
		$this->host->clear_pending();
		$this->assertFalse( $this->host->has_pending() );
		$this->assertSame( array(), $this->host->get_pending() );
	}
}
