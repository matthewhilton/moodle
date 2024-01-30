<?php

namespace mod_quiz\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use Exception;
use invalid_parameter_exception;
use mod_quiz\override_manager;
use mod_quiz\quiz_settings;

class upsert_overrides extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'overrides' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID of existing override (if updating)', VALUE_DEFAULT, null),
                    'quizid' => new external_value(PARAM_INT, 'ID of quiz to upsert override for'),
                    'groupid' => new external_value(PARAM_INT, 'ID of group', VALUE_DEFAULT, null),
                    'userid' => new external_value(PARAM_INT, 'ID of user', VALUE_DEFAULT, null),
                    'timeopen' => new external_value(PARAM_INT, 'Quiz override opening timestamp', VALUE_DEFAULT, null),
                    'timeclose' => new external_value(PARAM_INT, 'Quiz override closing timestamp', VALUE_OPTIONAL, null),
                    'timelimit' => new external_value(PARAM_INT, 'Quiz override time limit', VALUE_DEFAULT, null),
                    'attempts' => new external_value(PARAM_INT, 'Quiz override attempt count', VALUE_DEFAULT, null),
                    'password' => new external_value(PARAM_TEXT, 'Quiz override password', VALUE_DEFAULT, null),
                ]),
            ),
        ]);
    }

    public static function execute($overrides) {
        // TODO validation stuff.
        return array_map(function($override) {
            try {
                if (empty($override['quizid'])) {
                    throw new invalid_parameter_exception("Quiz ID was not given");
                }

                $quizobj = quiz_settings::create($override['quizid']);
                $manager = new override_manager($quizobj);
                $id = $manager->upsert_override($override);
                return ['id' => $id, 'error' => null];
            } catch (Exception $e) {
                return ['id' => null, 'error' => $e->getMessage()];
            }
        }, $overrides);
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id of the override created/updated. Null if error', VALUE_DEFAULT, null),
                'error' => new external_value(PARAM_TEXT, 'Error message, if there was an error', VALUE_DEFAULT, null),
            ])
        );
    }
}
