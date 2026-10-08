<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WEM_Public_Invitation_Renderer
{
    public function render(array $projection)
    {
        $expected_keys = array('guest_name', 'event_name');

        if (array_keys($projection) !== $expected_keys) {
            return '';
        }

        if (
            !is_string($projection['guest_name'])
            || !is_string($projection['event_name'])
        ) {
            return '';
        }

        $guest_name = esc_html($projection['guest_name']);
        $event_name = esc_html($projection['event_name']);

        return sprintf(
            '<main><h1>%s</h1><p>%s</p></main>',
            $guest_name,
            $event_name
        );
    }
}
