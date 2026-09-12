<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api\Controllers;

use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Helpers\ResponseHelper;

class AnalyticsSettingsController
{
    const OPTION_NAME = 'wp-toolkit_analytics_opted_in';

    /**
     * Update analytics opt-in status
     *
     * @return \WP_REST_Response
     */
    public function updateOptInStatus(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        $isOptedIn = isset($params['isOptedIn']) ? (bool) $params['isOptedIn'] : null;

        if ($isOptedIn === null) {
            return ResponseHelper::error('Missing required parameter: isOptedIn');
        }

        $updated = update_option(self::OPTION_NAME, $isOptedIn);
        // If the option does not exist in DB, and new value is false, then update_option do not create it.
        // So we need to add this option with value 'false' manually.
        if (!$updated) {
            add_option(self::OPTION_NAME, $isOptedIn);
        }

        if (get_option(self::OPTION_NAME) !== $isOptedIn) {
            return ResponseHelper::error('Failed to update analytics opt-in status');
        }

        return ResponseHelper::success([
            'isOptedIn' => $isOptedIn,
        ]);
    }
}
