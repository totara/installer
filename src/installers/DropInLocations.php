<?php

namespace TotaraInstaller\installers;

/**
 * Map of keys to drop in locations
 */
trait DropInLocations {
    /**
     * Locations of Totara plugin entrypoints
     *
     * @var array|string[]
     */
    protected static array $drop_in_locations = array(
        'client'             => 'client/component/{$name}/',
        'mod'                => 'server/mod/{$name}/',
        'atto'               => 'server/lib/editor/atto/plugins/{$name}/',
        'weka'               => 'server/lib/editor/weka/extensions/{$name}/',
        'tool'               => 'server/admin/tool/{$name}/',
        'approvalform'       => 'server/mod/approval/form/{$name}/',
        'assignsubmission'   => 'server/mod/assign/submission/{$name}/',
        'assignfeedback'     => 'server/mod/assign/feedback/{$name}/',
        'antivirus'          => 'server/lib/antivirus/{$name}/',
        'auth'               => 'server/auth/{$name}/',
        'availability'       => 'server/availability/condition/{$name}/',
        'block'              => 'server/blocks/{$name}/',
        'booktool'           => 'server/mod/book/tool/{$name}/',
        'cachestore'         => 'server/cache/stores/{$name}/',
        'cachelock'          => 'server/cache/locks/{$name}/',
        'calendartype'       => 'server/calendar/type/{$name}/',
        'customfield'        => 'server/totara/customfield/field/{$name}/',
        'format'             => 'server/course/format/{$name}/',
        'datafield'          => 'server/mod/data/field/{$name}/',
        'taxexport'          => 'server/totara/core/tabexport/{$name}/',
        'datapreset'         => 'server/mod/data/preset/{$name}/',
        'editor'             => 'server/lib/editor/{$name}/',
        'enrol'              => 'server/enrol/{$name}/',
        'filter'             => 'server/filter/{$name}/',
        'gradeexport'        => 'server/grade/export/{$name}/',
        'gradeimport'        => 'server/grade/import/{$name}/',
        'gradereport'        => 'server/grade/report/{$name}/',
        'gradingform'        => 'server/grade/grading/form/{$name}/',
        'local'              => 'server/local/{$name}/',
        'logstore'           => 'server/admin/tool/log/store/{$name}/',
        'ltisource'          => 'server/mod/lti/source/{$name}/',
        'ltiservice'         => 'server/mod/lti/service/{$name}/',
        'media'              => 'server/media/player/{$name}/',
        'message'            => 'server/message/output/{$name}/',
        'ml'                 => 'server/ml/{$name}/',
        'plagiarism'         => 'server/plagiarism/{$name}/',
        'portfolio'          => 'server/portfolio/{$name}/',
        'qbehaviour'         => 'server/question/behaviour/{$name}/',
        'qformat'            => 'server/question/format/{$name}/',
        'qtype'              => 'server/question/type/{$name}/',
        'quizaccess'         => 'server/mod/quiz/accessrule/{$name}/',
        'quiz'               => 'server/mod/quiz/report/{$name}/',
        'report'             => 'server/report/{$name}/',
        'repository'         => 'server/repository/{$name}/',
        'scormreport'        => 'server/mod/scorm/report/{$name}/',
        'search'             => 'server/search/engine/{$name}/',
        'theme'              => 'server/theme/{$name}/',
        'profilefield'       => 'server/user/profile/field/{$name}/',
        'webservice'         => 'server/webservice/{$name}/',
        'workshopallocation' => 'server/mod/workshop/allocation/{$name}/',
        'workshopeval'       => 'server/mod/workshop/eval/{$name}/',
        'workshopform'       => 'server/mod/workshop/form/{$name}/'
    );

    /**
     * @param string $packageType
     * @return string|null
     */
    protected static function getLocationFromPackageType(string $packageType): ?string {
        if (preg_match('/^totara-([a-z]+)$/', $packageType, $matches)) {
            $key = strtolower($matches[1]);
            return self::$drop_in_locations[$key] ?? null;
        }

        return null;
    }
}