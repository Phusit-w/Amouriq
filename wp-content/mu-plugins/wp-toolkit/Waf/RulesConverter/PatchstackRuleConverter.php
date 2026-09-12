<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Waf\RulesConverter;

final class PatchstackRuleConverter implements RuleConverterInterface
{
    public static function convert($id, array $rule)
    {
        return [
            'id'    => $id,
            'rules' => $rule['rules'],
            'type'  => 'BLOCK',
        ];
    }
}
