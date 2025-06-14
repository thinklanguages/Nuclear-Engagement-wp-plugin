<?php
namespace NuclearEngagement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class Loader {
    protected $actions = array();
    protected $filters = array();

    public function nuclen_add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->nuclen_add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    public function nuclen_add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->nuclen_add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    private function nuclen_add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );
        return $hooks;
    }

    public function nuclen_run() {
        // Register all filters
        foreach ( $this->filters as $hook ) {
            add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }

        // Register all actions
        foreach ( $this->actions as $hook ) {
            add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }
    }
}
