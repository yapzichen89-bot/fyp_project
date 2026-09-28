<?php
/**
 * Combined timetable rules and course catalog.
 * This file keeps all student and semester logic in one place so the project is easier to manage.
 */

function loadStudentRules()
{
    return [
        'semester_by_year' => [
            24 => 1,
            25 => 2,
            26 => 3,
            27 => 4,
            28 => 5,
        ],
        'default_semester' => 1,
        'semester_label_prefix' => 'Semester',
    ];
}

function getCourseCreditCatalog()
{
    return [
        'MPU22071' => ['name' => 'KURSUS INTEGRITI DAN ANTI RASUAH', 'credit' => 1.0],
        'MPU22153' => ['name' => 'ENGLISH FOR DIGITAL TECHNOLOGY', 'credit' => 3.0],
        'MPU21072' => ['name' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'credit' => 2.0],
        'MPU23162' => ['name' => 'PENGAJIAN ISLAM', 'credit' => 2.0],
        'MPU23272' => ['name' => 'PENDIDIKAN MORAL', 'credit' => 2.0],
        'DBM20153' => ['name' => 'DISCRETE MATHEMATICS', 'credit' => 3.0],
        'DBM10143' => ['name' => 'CALCULUS AND ALGEBRA', 'credit' => 3.0],
        'DBM30263' => ['name' => 'STATISTICS AND PROBABILITY', 'credit' => 3.0],
        'DFC20313' => ['name' => 'CYBERSECURITY FUNDAMENTALS', 'credit' => 3.0],
        'DFC20303' => ['name' => 'PROGRAMMING FUNDAMENTALS', 'credit' => 3.0],
        'DFC10353' => ['name' => 'PROGRAMMING FUNDAMENTALS', 'credit' => 3.0],
        'DFC20293' => ['name' => 'NETWORK AND DATA COMMUNICATION', 'credit' => 3.0],
        'DFC20283' => ['name' => 'DATABASE FUNDAMENTALS', 'credit' => 3.0],
        'DFC10273' => ['name' => 'OPERATING SYSTEMS', 'credit' => 3.0],
        'DFC10263' => ['name' => 'COMPUTER ARCHITECTURE', 'credit' => 3.0],
        'DFK50093' => ['name' => 'COMPUTER NETWORK SECURITY', 'credit' => 3.0],
        'DFN50563' => ['name' => 'ADVANCED SERVER ADMINISTRATION', 'credit' => 3.0],
        'DFP50463' => ['name' => 'JAVA-BASED APPLICATION DEVELOPMENT', 'credit' => 3.0],
        'DFN40543' => ['name' => 'ENTERPRISE NETWORK', 'credit' => 3.0],
        'DFK50083' => ['name' => 'PYTHON PROGRAMMING', 'credit' => 3.0],
        'DFP40443' => ['name' => 'FULL STACK WEB DEVELOPMENT', 'credit' => 3.0],
        'DFP30313' => ['name' => 'DIGITAL MULTIMEDIA', 'credit' => 3.0],
        'DFN40553' => ['name' => 'STRUCTURED CABLING IN NETWORKING', 'credit' => 3.0],
        'DFN40503' => ['name' => 'EMBEDDED INTERNET OF THINGS (IOT)', 'credit' => 3.0],
        'DFK40073' => ['name' => 'CLOUD COMPUTING', 'credit' => 3.0],
        'DFP40453' => ['name' => 'MOBILE APPLICATION DEVELOPMENT', 'credit' => 3.0],
        'DFP40433' => ['name' => 'BUSINESS INTELLIGENCE', 'credit' => 3.0],
        'DFK40063' => ['name' => 'SERVER ADMINISTRATION', 'credit' => 3.0],
        'DFC40343' => ['name' => 'SYSTEM ANALYSIS AND DESIGN FUNDAMENTALS', 'credit' => 3.0],
        'DFN30533' => ['name' => 'SWITCHING AND ROUTING ESSENTIALS', 'credit' => 3.0],
        'DFK30023' => ['name' => 'USER EXPERIENCE FUNDAMENTALS', 'credit' => 3.0],
        'DFK30053' => ['name' => 'OBJECT ORIENTED PROGRAMMING', 'credit' => 3.0],
        'DFK30033' => ['name' => 'CYBERPRENEURSHIP', 'credit' => 3.0],
        'DFC30333' => ['name' => 'ETHICS IN COMPUTING', 'credit' => 3.0],
        'DFK20013' => ['name' => 'WEB DESIGN TECHNOLOGY', 'credit' => 3.0],
    ];
}

function extractSubjectCode($subjectText)
{
    if (!is_string($subjectText)) {
        return '';
    }

    $cleanText = trim($subjectText);
    if ($cleanText === '') {
        return '';
    }

    if (preg_match('/\b([A-Z]{2,}\d{3,5})\b/', strtoupper($cleanText), $matches)) {
        return strtoupper($matches[1]);
    }

    $catalog = getCourseCreditCatalog();
    foreach ($catalog as $code => $course) {
        $codeUpper = strtoupper((string)$code);
        $nameUpper = strtoupper((string)($course['name'] ?? ''));
        if (stripos($cleanText, $codeUpper) !== false || stripos($cleanText, $nameUpper) !== false) {
            return $codeUpper;
        }
    }

    return '';
}

function getTimetableCreditTotal($timetableData)
{
    if (!is_array($timetableData)) {
        return 0.0;
    }

    $catalog = getCourseCreditCatalog();
    $seenCodes = [];
    $total = 0.0;

    foreach ($timetableData as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $rawCode = trim((string)($entry['code'] ?? ''));
        $rawSubject = trim((string)($entry['subject'] ?? ($entry['title'] ?? '')));
        $candidateCode = $rawCode !== '' ? strtoupper($rawCode) : extractSubjectCode($rawSubject);

        if ($candidateCode === '' || !isset($catalog[$candidateCode])) {
            continue;
        }

        if (isset($seenCodes[$candidateCode])) {
            continue;
        }

        $seenCodes[$candidateCode] = true;
        $total += (float)($catalog[$candidateCode]['credit'] ?? 0);
    }

    return round($total, 1);
}

function extractMatrixIdParts($matrixId)
{
    $cleanId = strtoupper(trim((string) $matrixId));

    if (preg_match('/(\d{2})([A-Z])\d{4,}/', $cleanId, $matches)) {
        return [
            'full_matrix_id' => $cleanId,
            'year_code' => (int) $matches[1],
            'letter' => $matches[2],
            'year_letter_pair' => $matches[1] . $matches[2],
        ];
    }

    return null;
}

function getSemesterFromMatrixId($matrixId)
{
    $rules = loadStudentRules();
    $parts = extractMatrixIdParts($matrixId);

    if ($parts === null) {
        return [
            'matrix_id' => trim((string) $matrixId),
            'semester' => $rules['default_semester'],
            'semester_label' => $rules['semester_label_prefix'] . ' ' . $rules['default_semester'],
            'status' => 'invalid_format',
        ];
    }

    $yearCode = $parts['year_code'];
    $semester = $rules['semester_by_year'][$yearCode] ?? $rules['default_semester'];

    return [
        'matrix_id' => $parts['full_matrix_id'],
        'year_code' => $yearCode,
        'letter' => $parts['letter'],
        'year_letter_pair' => $parts['year_letter_pair'],
        'semester' => $semester,
        'semester_label' => $rules['semester_label_prefix'] . ' ' . $semester,
        'status' => 'ok',
    ];
}

function detectStudentClassFromMatrixId($matrixId)
{
    $info = getSemesterFromMatrixId($matrixId);

    if ($info['status'] !== 'ok') {
        return $info;
    }

    return [
        'matrix_id' => $info['matrix_id'],
        'year_code' => $info['year_code'],
        'letter' => $info['letter'],
        'year_letter_pair' => $info['year_letter_pair'],
        'semester' => $info['semester'],
        'class_name' => 'S' . $info['semester'],
        'semester_label' => $info['semester_label'],
        'status' => 'ok',
    ];
}

function getSemesterCourseCatalog($semester)
{
    $catalog = [
        1 => [
            ['code' => 'DFT1013', 'name' => 'INTRODUCTION TO COMPUTER SYSTEM', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFC1025', 'name' => 'PROBLEM SOLVING AND PROGRAM DESIGN', 'credit' => 2.0, 'default_duration_hours' => 2],
            ['code' => 'DBM10143', 'name' => 'CALCULUS AND ALGEBRA', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'MPU22153', 'name' => 'ENGLISH FOR DIGITAL TECHNOLOGY', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'MPU24031', 'name' => 'SUKAN 1', 'credit' => 1.0, 'default_duration_hours' => 2],
            ['code' => 'MPU21072', 'name' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'credit' => 2.0, 'default_duration_hours' => 2],
        ],
        2 => [
            ['code' => 'DBM20153', 'name' => 'DISCRETE MATHEMATICS', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFC20283', 'name' => 'DATABASE FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFC20293', 'name' => 'NETWORK AND DATA COMMUNICATION', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFC20303', 'name' => 'PROGRAMMING FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'MPU23272', 'name' => 'PENDIDIKAN MORAL', 'credit' => 2.0, 'default_duration_hours' => 2],
            ['code' => 'MPU24051', 'name' => 'SUKAN 2', 'credit' => 1.0, 'default_duration_hours' => 2],
        ],
        3 => [
            ['code' => 'DBM30263', 'name' => 'STATISTICS AND PROBABILITY', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFC30133', 'name' => 'CYBERSECURITY FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFC30333', 'name' => 'ETHICS IN COMPUTING', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFK20013', 'name' => 'WEB DESIGN TECHNOLOGY', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFK30023', 'name' => 'USER EXPERIENCE FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFK30033', 'name' => 'CYBERPRENEURSHIP', 'credit' => 3.0, 'default_duration_hours' => 3],
        ],
        4 => [
            ['code' => 'DFC30433', 'name' => 'SYSTEM ANALYSIS AND DESIGN FUNDAMENTALS', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFK30053', 'name' => 'OBJECT ORIENTED PROGRAMMING', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFK40063', 'name' => 'SERVER ADMINISTRATION', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFK40073', 'name' => 'CLOUD COMPUTING', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFP30313', 'name' => 'DIGITAL MULTIMEDIA', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFP40443', 'name' => 'FULL STACK WEB DEVELOPMENT', 'credit' => 3.0, 'default_duration_hours' => 3],
        ],
        5 => [
            ['code' => 'DFK50083', 'name' => 'PYTHON PROGRAMMING', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFK50093', 'name' => 'COMPUTER NETWORK SECURITY', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFP40433', 'name' => 'BUSINESS INTELLIGENCE', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFP40453', 'name' => 'MOBILE APPLICATION DEVELOPMENT', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFP50463', 'name' => 'JAVA-BASED APPLICATION DEVELOPMENT', 'credit' => 3.0, 'default_duration_hours' => 3],
            ['code' => 'DFT50194', 'name' => 'INTEGRATED PROJECT', 'credit' => 4.0, 'default_duration_hours' => 3],
            ['code' => 'MPU21072', 'name' => 'PENGHAYATAN ETIKA DAN PERADABAN', 'credit' => 2.0, 'default_duration_hours' => 2],
            ['code' => 'MPU22071', 'name' => 'KURSUS INTEGRITI DAN ANTI RASUAH', 'credit' => 1.0, 'default_duration_hours' => 2],
        ],
    ];

    return $catalog[$semester] ?? $catalog[1];
}

function getSemesterCreditTotal($semester)
{
    $courses = getSemesterCourseCatalog($semester);
    $total = 0.0;

    foreach ($courses as $course) {
        $total += (float) ($course['credit'] ?? 0);
    }

    return $total;
}
