<?php

/*
 * Loyalty points. The numbers live HERE and nowhere else: the apps only relay what
 * a member did (an answer was right or wrong) and the backend decides the points.
 * The app shows the same figures while offline (`QUIZ_POINTS` in its utils/quizStore.ts) and
 * must be updated together with this file.
 */
return [
    'quiz' => [
        'points_right' => (int) env('LOYALTY_QUIZ_POINTS_RIGHT', 10),
        'points_wrong' => (int) env('LOYALTY_QUIZ_POINTS_WRONG', -10),
        /* One request carries what a phone collected while offline; more than this is a client gone wrong. */
        'max_answers_per_request' => 100,
        /*
         * How many plays of one section a member gets per day (the app's day ends at midnight, app timezone here). Points
         * of a play beyond that are not booked. The app mirrors this number (`PLAYS_PER_DAY` in utils/quizLimit.ts).
         */
        'plays_per_day' => (int) env('LOYALTY_QUIZ_PLAYS_PER_DAY', 5),
        /* The quiz sections (a `quiz` key sent with each answer). Each starts with the badges below. */
        'sections' => ['mcq:football', 'mcq:fawazeer', 'mcq:general', 'mcq:history'],
        /*
         * Three kinds of badge with five levels each (15 per section) are the starting set. Only a SEED: the migration copies these into `quiz_badge_definitions`, and from
         * then on the admin edits them at /admin/loyalty (title, icon, threshold, reward points, on/off).
         * Format: kind => [threshold => reward loyalty points]. `solved` = right answers in total, `streak` = the longest
         * run of right answers in a row (a wrong answer or a missed clock breaks it), `stage` = the stage reached (a stage
         * is one full pass over the section's questions; the app sends the stage the player is in AFTER each answer).
         */
        'badges' => [
            'solved' => [10 => 10, 50 => 50, 100 => 100, 500 => 300, 1000 => 600],
            'streak' => [5 => 10, 10 => 20, 20 => 40, 30 => 60, 50 => 120],
            'stage' => [2 => 50, 3 => 100, 4 => 150, 5 => 250, 10 => 500],
        ],
        /* The names the starting badges carry (kind => [threshold => title shown to the member]). Also only a seed. */
        'badge_titles' => [
            'solved' => [10 => 'بداية موفقة', 50 => 'متفوق', 100 => 'خبير الأسئلة', 500 => 'موسوعة متحركة', 1000 => 'أسطورة المعرفة'],
            'streak' => [5 => 'سلسلة صح', 10 => 'سلسلة قوية', 20 => 'سلسلة نارية', 30 => 'سلسلة لا تُكسر', 50 => 'سلسلة أسطورية'],
            'stage' => [2 => 'مرحلة جديدة', 3 => 'مستكشف', 4 => 'متقدم', 5 => 'محترف', 10 => 'سيد المراحل'],
        ],
    ],
];
