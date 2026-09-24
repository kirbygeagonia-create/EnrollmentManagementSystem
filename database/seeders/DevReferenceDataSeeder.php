<?php

namespace Database\Seeders;

use App\Enums\FeeUnitBasis;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Feetypes;
use App\Models\Majors;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Rooms;
use App\Models\Staffusers;
use App\Models\Subjects;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DEV-ONLY reconstruction of the local `ems` reference dataset.
 *
 * The original hand-built synthetic dataset (30k students) was not committed
 * to the repo and is not recoverable from git. This seeder recreates the
 * reference data the E2E walkthrough and AdminAccessSmoke tests read from the
 * live MySQL database (see AGENTS.md / audit §3.2). It is idempotent and is
 * NOT wired into DatabaseSeeder (CI uses empty test databases).
 *
 * Run on the dev database only:
 *
 *   php artisan db:seed --class=DevReferenceDataSeeder --force
 *   php artisan db:seed --force   # RbacSeeder + NotificationSeeder
 */
class DevReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Offices (fixed IDs are part of the domain model) ----------
        $offices = [
            1 => 'Registrar',
            2 => 'Accounting',
            3 => 'Scholarship',
            4 => 'Guidance',
            5 => 'Blocking',
            6 => 'Admission',
            7 => 'Academic Department',
            8 => 'Clearance',
            11 => 'Clinic',
            22 => 'ID Office',
        ];
        foreach ($offices as $id => $name) {
            Offices::firstOrCreate(['officeId' => $id], ['officeName' => $name]);
        }

        // ---------- Academic units (explicit IDs 1-6 so course refs stay stable).
        // Names follow the official SEAIT structure (SEAIT_Official_Information.md
        // §4.A) and are synced via updateOrCreate so a seeder re-run restores the
        // official baseline. ----------
        $units = [
            1 => 'College of Agriculture and Fisheries',
            2 => 'College of Criminal Justice Education',
            3 => 'College of Business and Governance',
            4 => 'College of Information and Communication Technology',
            5 => 'Department of Civil & Electrical Engineering',
            6 => 'College of Teacher Education',
        ];
        foreach ($units as $id => $name) {
            Academicunits::updateOrCreate(
                ['unitId' => $id],
                ['unitName' => $name, 'unitType' => 'college']
            );
        }

        // ---------- Religions ----------
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        // ---------- Academic years + terms (E2E expects term 18 = Summer) ----------
        $year1 = Academicyears::firstOrCreate(
            ['yearLabel' => '2024-2025'],
            ['startDate' => '2024-06-01', 'endDate' => '2025-05-31']
        );
        $year2 = Academicyears::firstOrCreate(
            ['yearLabel' => '2025-2026'],
            ['startDate' => '2025-06-01', 'endDate' => '2026-05-31']
        );
        $terms = [
            [1, $year1->academicYearId, '1st', '2024-06-01', '2024-10-31'],
            [2, $year1->academicYearId, '2nd', '2024-11-01', '2025-03-31'],
            [3, $year1->academicYearId, 'Summer', '2025-04-01', '2025-05-31'],
            [10, $year2->academicYearId, '1st', '2025-06-01', '2025-10-31'],
            [11, $year2->academicYearId, '2nd', '2025-11-01', '2026-03-31'],
            [18, $year2->academicYearId, 'Summer', '2026-04-01', '2026-05-31'],
        ];
        foreach ($terms as [$id, $yid, $sem, $s, $e]) {
            Academicterms::firstOrCreate(
                ['termId' => $id],
                ['academicYearId' => $yid, 'semester' => $sem, 'startDate' => $s, 'endDate' => $e]
            );
        }

        // ---------- Clearance periods (E2E reads the open period for term 18) ----------
        Clearanceperiods::firstOrCreate(
            ['termId' => 18],
            ['clearanceStartDate' => '2026-04-01', 'clearanceEndDate' => '2026-05-31', 'periodStatus' => 'open']
        );

        // ---------- Courses.
        // The program catalog follows the official SEAIT offering
        // (SEAIT_Official_Information.md §4.A). Existing demo rows keep their
        // explicit IDs (E2E uses ids 1, 3, 5) and are synced via updateOrCreate;
        // programs added in this expansion are keyed by their unique courseCode
        // so they never clobber an admin-created course. BSCrim carries the
        // documented SCAT entrance + retention requirements (§4.A.2); BSSW is
        // the board program whose board tracks take the department-specific
        // cutoff (§8 Step 3) and a retention exam (BR10 — mainly board courses).
        // BSCS was dropped: it is not part of the official SEAIT offering.
        $courses = [
            [1, 4, 'BSIT', 'Bachelor of Science in Information Technology', false, false],
            [3, 2, 'BSCrim', 'Bachelor of Science in Criminology', true, true],
            [4, 1, 'BSA', 'Bachelor of Science in Agriculture', false, false],
            [5, 3, 'BSBA', 'Bachelor of Science in Business Administration', false, true],
            [6, 6, 'BSEd', 'Bachelor of Secondary Education', false, false],
        ];
        foreach ($courses as [$id, $unitId, $code, $name, $exam, $retention]) {
            Courses::updateOrCreate(
                ['courseId' => $id],
                [
                    'unitId' => $unitId,
                    'courseCode' => $code,
                    'courseName' => $name,
                    'requiresEntranceExam' => $exam,
                    'requiresRetentionExam' => $retention,
                ]
            );
        }

        $expandedCourses = [
            // College of Business and Good Governance (CBGG)
            ['BPA', 3, 'Bachelor of Public Administration', false, false],
            ['BSAIS', 3, 'Bachelor of Science in Accounting Information Systems', false, false],
            ['BSHM', 3, 'Bachelor of Science in Hospitality Management', false, false],
            ['BSSW', 3, 'Bachelor of Science in Social Work', true, true],
            ['BSTM', 3, 'Bachelor of Science in Tourism Management', false, false],
            // College of Information and Communication Technology (CICT) —
            // Business Analytics is a BSIT major, not a separate course.
            // Department of Civil & Electrical Engineering
            ['BSCE', 5, 'Bachelor of Science in Civil Engineering', false, false],
            ['BSEE', 5, 'Bachelor of Science in Electrical Engineering', false, false],
            // College of Agriculture and Fisheries (CAF)
            ['BSFish', 1, 'Bachelor of Science in Fisheries', false, false],
            // College of Teacher Education (CTE)
            ['BEEd', 6, 'Bachelor of Elementary Education', false, false],
            ['BECEd', 6, 'Bachelor of Early Childhood Education', false, false],
            ['BTLEd', 6, 'Bachelor of Technology and Livelihood Education', false, false],
        ];
        foreach ($expandedCourses as [$code, $unitId, $name, $exam, $retention]) {
            Courses::firstOrCreate(
                ['courseCode' => $code],
                [
                    'unitId' => $unitId,
                    'courseName' => $name,
                    'requiresEntranceExam' => $exam,
                    'requiresRetentionExam' => $retention,
                ]
            );
        }

        // ---------- Majors (documented specializations of the degree programs).
        // The Evaluation flow filters the curriculum by the enrollment's majorId
        // when set, so these are real flow data — not display-only. ----------
        $majorsByCourse = [
            'BSA' => ['Animal Science', 'Crop Science', 'Horticulture', 'Plant Breeding and Genetics'],
            'BSEd' => ['English', 'Filipino', 'Mathematics', 'Science', 'Social Studies'],
            'BSBA' => ['Marketing Management'],
            'BSIT' => ['Business Analytics'],
            'BTLEd' => ['Information and Communication Technology'],
        ];
        foreach ($majorsByCourse as $courseCode => $majorNames) {
            $course = Courses::where('courseCode', $courseCode)->first();
            if ($course === null) {
                continue;
            }
            foreach ($majorNames as $majorName) {
                Majors::firstOrCreate(
                    ['courseId' => $course->courseId, 'majorName' => $majorName]
                );
            }
        }

        // ---------- Subjects catalog. Every subject carries its code, name,
        // description, and the lecture/lab unit breakdown. The CHED GE pool
        // and program subjects follow the official curricula mapped below;
        // GEN101/ENG101/MATH101/COM101 are synthetic placeholders that
        // existing demo rows still reference. ----------
        $subjects = [
            // [code, name, description, type, lectureUnits, labUnits]
            // --- CHED General Education pool (shared by every curriculum) ---
            ['UT1', 'Understanding the Self', 'Identity, self-knowledge, and personal development across the lifespan', 'lecture', 3, 0],
            ['TC1', 'The Contemporary World', 'Globalization and its impact on societies, economies, and governance', 'lecture', 3, 0],
            ['MM1', 'Mathematics in the Modern World', 'Mathematical tools for reasoning, data analysis, and real-world problems', 'lecture', 3, 0],
            ['PC1', 'Purposive Communication', 'Communication for specific audiences and purposes across contexts and media', 'lecture', 3, 0],
            ['RH1', 'Readings in Philippine History', 'Philippine history analyzed through primary sources and historiography', 'lecture', 3, 0],
            ['AA1', 'Art Appreciation', 'Art elements, movements, and the role of art in society and culture', 'lecture', 3, 0],
            ['ET1', 'Ethics', 'Moral principles, ethical reasoning, and responsible decision-making', 'lecture', 3, 0],
            ['ST1', 'Science, Technology and Society', 'How science and technology shape society, values, and the environment', 'lecture', 3, 0],
            ['RZ1', 'Life and Works of Rizal', "Rizal's life, writings, and their relevance to Philippine nationhood", 'lecture', 3, 0],
            ['PE1', 'Physical Education 1', 'Movement competency and fundamental fitness training', 'lecture', 2, 0],
            ['PE2', 'Physical Education 2', 'Rhythmic activities and exercise-based fitness routines', 'lecture', 2, 0],
            ['PE3', 'Physical Education 3', 'Individual and dual sports with fitness assessment', 'lecture', 2, 0],
            ['PE4', 'Physical Education 4', 'Team sports and lifelong recreational activities', 'lecture', 2, 0],
            ['NSTP1', 'National Service Training Program 1', 'Civic welfare, literacy, or military training service orientation', 'lecture', 3, 0],
            ['NSTP2', 'National Service Training Program 2', 'Community service immersion and civic engagement projects', 'lecture', 3, 0],
            // --- CMO 75 s. 2017 Education core (shared by the CTE programs) ---
            ['ED101', 'The Child and Adolescent Learners and Learning Principles', 'Learner development and the principles that drive learning', 'lecture', 3, 0],
            ['ED102', 'The Teaching Profession', 'Teacher ethics, professionalism, and the demands of the teaching career', 'lecture', 3, 0],
            ['ED103', 'Facilitating Learner-Centered Teaching', 'Learner-centered pedagogy and classroom facilitation strategies', 'lecture', 3, 0],
            ['ED104', 'Technology for Teaching and Learning 1', 'Digital tools and media for effective instruction', 'both', 2, 1],
            ['ED105', 'Assessment of Learning 1', 'Principles and tools for assessing student learning', 'lecture', 3, 0],
            ['ED106', 'Building and Enhancing New Literacies Across the Curriculum', 'Multiliteracies integrated across subject areas', 'lecture', 3, 0],
            // --- Legacy synthetic placeholders (existing demo rows reference them) ---
            ['GEN101', 'General Education 1', 'Synthetic placeholder subject from the original demo dataset', 'lecture', 3, 0],
            ['ENG101', 'English 1', 'Synthetic placeholder subject from the original demo dataset', 'lecture', 3, 0],
            ['MATH101', 'Mathematics 1', 'Synthetic placeholder subject from the original demo dataset', 'lecture', 3, 0],
            ['COM101', 'Communication Skills', 'Synthetic placeholder subject from the original demo dataset', 'lecture', 3, 0],
            ['CRIM101', 'Introduction to Criminology', 'Nature, causes, and extent of crime and criminal behavior', 'lecture', 3, 0],
            ['IT101', 'Introduction to Computing', 'Fundamentals of computing, hardware, software, and the IT profession', 'lecture', 3, 0],
            // --- BSIT program subjects (UE CIT2019) ---
            ['CCP1101', 'Computer Programming 1', 'Structured programming fundamentals and problem solving', 'both', 2, 1],
            ['CSP1101', 'Social and Professional Issues in Computing', 'Ethics, professionalism, and the social impact of computing', 'lecture', 3, 0],
            ['CCP1102', 'Computer Programming 2', 'Object-oriented programming and advanced problem solving', 'both', 2, 1],
            ['CDS1101', 'Data Structures and Algorithms', 'Lists, trees, graphs, and algorithmic complexity', 'lecture', 3, 0],
            ['CFD1101', 'Fundamentals of Database Systems', 'Relational database design, SQL, and normalization', 'both', 2, 1],
            ['CBM1101', 'Business Process Management', 'Modeling, analyzing, and improving business processes', 'lecture', 3, 0],
            ['CCP1103', 'Computer Programming 3', 'Advanced programming paradigms and frameworks', 'both', 2, 1],
            ['CDM1101', 'Discrete Mathematics for ITE', 'Logic, sets, combinatorics, and graph theory for computing', 'lecture', 3, 0],
            ['CIM1101', 'Information Management', 'Information governance, storage, and retrieval systems', 'lecture', 3, 0],
            ['CIT2101', 'Application of Statistics in IT', 'Statistical analysis applied to computing and data problems', 'lecture', 3, 0],
            ['CIT2102', 'Hardware Software and Peripherals', 'Computer hardware assembly, configuration, and troubleshooting', 'both', 2, 1],
            ['CDE1101', 'Applications Development and Emerging Technologies', 'Modern application development with emerging platforms', 'both', 2, 1],
            ['CDT1101', 'Data Analytics', 'Data wrangling, visualization, and business analytics techniques', 'both', 2, 1],
            ['CHC1101', 'Human Computer Interaction', 'Usability, interface design, and user experience principles', 'lecture', 3, 0],
            ['CIA1101', 'Information Assurance and Security 1', 'Security principles, threats, and protection mechanisms', 'lecture', 3, 0],
            ['CIP1101', 'Integrative Programming and Technologies 1', 'Integrating systems, APIs, and scripting technologies', 'both', 2, 1],
            ['CIT2201', 'Networking 1', 'Network fundamentals, protocols, and configuration', 'both', 2, 1],
            ['CSA1101', 'Systems Analysis Design and Prototyping', 'Requirements analysis, system design, and prototyping', 'lecture', 3, 0],
            ['CIP1102', 'Integrative Programming and Technologies 2', 'Advanced systems integration and scripting technologies', 'both', 2, 1],
            ['CIT2301', 'Platform Technologies', 'Operating systems, virtualization, and computing platforms', 'lecture', 3, 0],
            // --- BSCrim program subjects (CMO 5 s. 2018) ---
            ['CLJ1', 'Introduction to Philippine Justice System', 'Philippine courts, prosecution, law enforcement, and corrections', 'lecture', 3, 0],
            ['KP1', 'Kulturang Popular', 'Popular na kultura at ang kaugnayan nito sa lipunang Pilipino', 'lecture', 3, 0],
            ['GS1', 'Gender and Society', 'Gender roles, equality, and social relations', 'lecture', 3, 0],
            ['GE1', 'Entrepreneurial Mind Setting', 'Entrepreneurial thinking and opportunity recognition', 'lecture', 3, 0],
            ['LE1', 'Living in the IT Era', 'Technology and its role in everyday life and work', 'lecture', 3, 0],
            ['CRIM2', 'Theories of Crime Causation', 'Criminological theories explaining criminal behavior', 'lecture', 3, 0],
            ['LEA1', 'Law Enforcement Administration', 'Organization and administration of law enforcement agencies', 'lecture', 3, 0],
            ['LEA2', 'Comparative Models in Policing', 'Policing models across different countries and systems', 'lecture', 3, 0],
            ['CDI1', 'Fundamentals of Criminal Investigation and Intelligence', 'Investigation procedures, intelligence gathering, and evidence', 'lecture', 3, 0],
            ['FR1', 'Forensic Photography', 'Photographic documentation of crime scenes and evidence', 'both', 2, 1],
            ['CHEM1', 'General Chemistry (Organic)', 'Basic organic chemistry for forensic and laboratory work', 'lecture', 3, 0],
            ['CRIM3', 'Human Behavior and Victimology', 'Human behavior patterns and victim-offender dynamics', 'lecture', 3, 0],
            ['CRIM4', 'Professional Conduct and Ethical Standards', 'Professional ethics and conduct standards for criminologists', 'lecture', 3, 0],
            ['CA1', 'Institutional Correction', 'Correctional institutions and rehabilitation systems', 'lecture', 3, 0],
            ['CDI2', 'Specialized Crime Investigation 1 with Legal Medicine', 'Specialized investigation techniques with legal medicine', 'lecture', 3, 0],
            ['LEA3', 'Introduction to Industrial Security Concepts', 'Industrial security concepts and practices', 'lecture', 3, 0],
            ['FR2', 'Personal Identification Techniques', 'Fingerprinting and personal identification methods', 'both', 2, 1],
            ['CFLM1', 'Character Formation 1', 'Nationalism, patriotism, and character formation', 'lecture', 3, 0],
            // --- BSBA-MM program subjects (UE BBMM2018) ---
            ['BBC1101', 'Basic Microeconomics', 'Supply, demand, markets, and consumer economics', 'lecture', 3, 0],
            ['BBC1109', 'Human Resources Management', 'Recruitment, development, and management of people at work', 'lecture', 3, 0],
            ['BMM1101', 'Essentials of Marketing Management', 'Marketing principles, strategy, and the marketing mix', 'lecture', 3, 0],
            ['BBC1106', 'Essentials of Accounting', 'Financial statements, bookkeeping, and accounting cycles', 'lecture', 3, 0],
            ['BMM1201', 'Consumer Behavior', 'How consumers think, decide, and buy', 'lecture', 3, 0],
            ['BBC1108', 'Good Governance and Social Responsibility', 'Corporate governance, ethics, and social responsibility', 'lecture', 3, 0],
            ['BMM2101', 'Professional Salesmanship', 'Selling techniques, negotiation, and client relationships', 'lecture', 3, 0],
            ['BBC1201', 'Quantitative Techniques in Business', 'Statistical and quantitative tools for business decisions', 'lecture', 3, 0],
            ['BBC1202', 'Taxation (Income Taxation)', 'Income taxation principles and compliance', 'lecture', 3, 0],
            ['BBC4980', 'Business Research', 'Business research methods and report writing', 'lecture', 3, 0],
            ['BMM2201', 'Product Management', 'Product planning, pricing, and lifecycle management', 'lecture', 3, 0],
            ['BMM2202', 'Distribution Management', 'Distribution channels, logistics, and retail strategy', 'lecture', 3, 0],
            ['ZPD1', 'Personality Development', 'Professional image, demeanor, and workplace etiquette', 'lecture', 3, 0],
            // --- BSEd specialization subjects (CMO 75 s. 2017) ---
            ['EN1', 'Basic English Grammar', 'Grammar structures and usage for language teaching', 'lecture', 3, 0],
            ['FI1', 'Pagbasa at Pagsulat', 'Pagbasa at pagsulat sa Filipino bilang wika ng pagtuturo', 'lecture', 3, 0],
            ['MA2', 'Fundamentals of Mathematics', 'Number theory, algebra, and problem solving for teachers', 'lecture', 3, 0],
            ['SC1', 'Human Anatomy and Physiology', 'Human body systems for science teaching', 'both', 2, 1],
            ['SS1', 'Foundations of Social Studies', 'Society, culture, and civic life as subject matter', 'lecture', 3, 0],
            // --- BSHM/BSTM program subjects (UE ABSHM2019 / CMO 62 s. 2017) ---
            ['ATH1103', 'Macro Perspective of Tourism and Hospitality', 'Tourism and hospitality as a system and its economic impact', 'lecture', 3, 0],
            ['ATH1110', 'Risk Management as Applied to Safety, Security and Sanitation', 'Risk, safety, security, and sanitation management in hospitality', 'lecture', 3, 0],
            ['ATH1104', 'Micro Perspective of Tourism and Hospitality', 'Business operations of each tourism and hospitality sector', 'lecture', 3, 0],
            ['ATH1107', 'Philippine Tourism, Culture and Geography', 'Philippine destinations, culture, and geographic context', 'lecture', 3, 0],
            ['AHM1201', 'Kitchen Essentials and Basic Food Preparation', 'Kitchen operations and basic food preparation techniques', 'both', 2, 1],
            ['AHM1202', 'Fundamentals in Lodging Operations', 'Hotel front-line and back-of-house lodging operations', 'lecture', 3, 0],
            ['AHM2101', 'Applied Business Tools and Technologies', 'Property management systems and hospitality technology tools', 'both', 2, 1],
            ['AHM2102', 'Supply Chain Management in Tourism and Hospitality Industry', 'Procurement and supply chains for hospitality operations', 'lecture', 3, 0],
            ['AHE4305', 'Culinary Nutrition', 'Nutrition principles applied to menu and food preparation', 'lecture', 3, 0],
            ['AHE4302', 'Bakery Science', 'Baking science, ingredients, and production techniques', 'both', 2, 1],
            ['AHE4307', 'Housekeeping Procedures', 'Housekeeping standards, procedures, and room care', 'lecture', 3, 0],
            ['AHM2201', 'Fundamentals of Food Service Operations', 'Food service systems, service flow, and dining operations', 'both', 2, 1],
            ['ATH4301', 'Front Office Procedures', 'Guest reception, reservations, and front office workflows', 'lecture', 3, 0],
            ['TM1', 'Introduction to Culinary Arts', 'Culinary fundamentals for tourism professionals', 'both', 2, 1],
            ['TM2', 'Travel Agency and Tour Operations', 'Tour packaging, agency operations, and travel services', 'lecture', 3, 0],
            ['TM3', 'Transportation Management', 'Air, sea, and land transport systems for tourism', 'lecture', 3, 0],
            ['TM4', 'Events Management and MICE', 'Meetings, incentives, conferences, and events management', 'lecture', 3, 0],
            // --- BPA program subjects (CMO PSG) ---
            ['PA1', 'Introduction to Public Administration', 'Theory and practice of public administration and bureaucracy', 'lecture', 3, 0],
            ['PA2', 'Philippine Politics and Governance', 'Philippine political institutions and governance structures', 'lecture', 3, 0],
            ['PA3', 'Local Government Administration', 'Local government units, decentralization, and local governance', 'lecture', 3, 0],
            ['PA4', 'Public Personnel Administration', 'Civil service, staffing, and human resources in government', 'lecture', 3, 0],
            ['PA5', 'Public Fiscal Administration', 'Government budgeting, spending, and fiscal policy', 'lecture', 3, 0],
            // --- BSAIS program subjects (CMO PSG) ---
            ['AIS1', 'Fundamentals of Accounting and Bookkeeping', 'Bookkeeping cycle and basic financial records', 'lecture', 3, 0],
            ['AIS2', 'Financial Accounting and Reporting', 'Financial statement preparation and reporting standards', 'lecture', 3, 0],
            ['AIS3', 'Cost Accounting and Control', 'Costing systems and cost control for business', 'lecture', 3, 0],
            ['AIS4', 'Accounting Information Systems', 'Design and control of computerized accounting systems', 'both', 2, 1],
            ['AIS5', 'Business Analytics', 'Data-driven analysis for accounting and business decisions', 'lecture', 3, 0],
            // --- BSCE/BSEE shared engineering subjects (CMO PSG) ---
            ['MATH2', 'Calculus 1', 'Limits, derivatives, and their engineering applications', 'lecture', 3, 0],
            ['CHEM2', 'Chemistry for Engineers', 'Materials chemistry and engineering applications', 'both', 2, 1],
            ['PHYS2', 'Physics for Engineers', 'Mechanics, electricity, and engineering physics', 'both', 2, 1],
            ['CE1', 'Introduction to Civil Engineering', 'Civil engineering disciplines, ethics, and practice', 'lecture', 3, 0],
            ['CE2', 'Computer-Aided Drafting', 'CAD tools for engineering plans and drawings', 'both', 1, 2],
            ['CE3', 'Engineering Mechanics', 'Forces, equilibrium, and structural statics', 'lecture', 3, 0],
            ['CE4', 'Surveying 1', 'Field measurement, leveling, and traverse computation', 'both', 2, 1],
            ['CE5', 'Strength of Materials', 'Stress, strain, and material behavior under load', 'lecture', 3, 0],
            ['EE1', 'Introduction to Electrical Engineering', 'Electrical engineering fields, ethics, and practice', 'lecture', 3, 0],
            ['EE2', 'Computer Fundamentals and Programming', 'Computing fundamentals and programming for engineers', 'both', 2, 1],
            ['EE3', 'Circuit Theory 1', 'Resistive circuits, network theorems, and analysis', 'lecture', 3, 0],
            ['EE4', 'Circuit Theory 2', 'AC circuits, phasors, and frequency response', 'lecture', 3, 0],
            ['EE5', 'Electromagnetics', 'Electric and magnetic fields and wave propagation', 'lecture', 3, 0],
            // --- BSA program subjects (CMO PSG) ---
            ['AG1', 'Introduction to Agriculture', 'Agricultural systems, careers, and production fundamentals', 'lecture', 3, 0],
            ['AG2', 'Principles of Crop Science', 'Crop growth, production, and management principles', 'lecture', 3, 0],
            ['AG3', 'Principles of Animal Science', 'Animal physiology, nutrition, and livestock management', 'lecture', 3, 0],
            ['AG4', 'Fundamentals of Soil Science', 'Soil properties, fertility, and land management', 'both', 2, 1],
            ['AG5', 'Principles of Horticulture', 'Fruit, vegetable, and ornamental crop production', 'lecture', 3, 0],
            ['AG6', 'Plant Breeding and Genetics', 'Genetic principles and plant improvement techniques', 'lecture', 3, 0],
            // --- BSFish program subjects (CMO PSG) ---
            ['FS1', 'Introduction to Fisheries', 'Fisheries science, aquatic resources, and the fishing industry', 'lecture', 3, 0],
            ['FS2', 'Principles of Aquaculture', 'Aquaculture systems, species, and production methods', 'lecture', 3, 0],
            ['FS3', 'Aquatic Ecology', 'Aquatic ecosystems and the organisms within them', 'lecture', 3, 0],
            ['FS4', 'Fish Capture and Post-Harvest Fisheries', 'Capture fisheries and post-harvest handling technology', 'both', 2, 1],
            ['FS5', 'Fish Health Management', 'Fish diseases, prevention, and health management', 'lecture', 3, 0],
            // --- BEEd/BECEd/BTLEd program subjects (CMO PSG) ---
            ['BE1', 'Teaching English in the Elementary Grades', 'Language arts instruction for elementary learners', 'lecture', 3, 0],
            ['BE2', 'Teaching Mathematics in the Primary Grades', 'Mathematics instruction for young learners', 'lecture', 3, 0],
            ['BE3', 'Teaching Science in the Elementary Grades', 'Inquiry-based science for elementary classrooms', 'lecture', 3, 0],
            ['BE4', 'Teaching Filipino in the Elementary Grades', 'Pagtuturo ng Filipino sa elementarya', 'lecture', 3, 0],
            ['EC1', 'Foundations of Early Childhood Education', 'History, theory, and practice of early childhood education', 'lecture', 3, 0],
            ['EC2', 'Child Growth and Development', 'Physical, cognitive, and socio-emotional child development', 'lecture', 3, 0],
            ['EC3', 'Play and Developmentally Appropriate Practices', 'Play-based, developmentally appropriate teaching practices', 'lecture', 3, 0],
            ['EC4', 'Health, Nutrition and Wellness in Early Childhood', 'Child health, nutrition, and wellness in early settings', 'lecture', 3, 0],
            ['BT1', 'Foundations of Technology and Livelihood Education', 'TLE pedagogy, curriculum, and livelihood education theory', 'lecture', 3, 0],
            ['BT2', 'Computer Systems Servicing', 'Computer assembly, servicing, and network setup', 'both', 2, 1],
            ['BT3', 'Web Development and Internet Applications', 'Web design and development for livelihood education', 'both', 2, 1],
            ['BT4', 'Technical Drafting', 'Technical drawing and drafting for TLE instruction', 'both', 1, 2],
            ['BT5', 'Culinary Arts for Livelihood Education', 'Cookery and food trades for livelihood teaching', 'both', 2, 1],
            // --- BSSW program subjects (CMO PSG) ---
            ['SW1', 'Introduction to Social Work and Social Welfare', 'Social work profession, values, and welfare systems', 'lecture', 3, 0],
            ['SW2', 'Philippine Social Realities', 'Poverty, inequality, and social issues in the Philippines', 'lecture', 3, 0],
            ['SW3', 'Human Behavior and Social Environment', 'Human development across the social environment', 'lecture', 3, 0],
            ['SW4', 'Social Work Practice with Individuals', 'Casework methods and helping relationships', 'lecture', 3, 0],
            ['SW5', 'Social Work Practice with Groups', 'Group work methods and facilitation', 'lecture', 3, 0],
            ['SW6', 'Community Organizing and Development', 'Community organizing, participation, and development', 'lecture', 3, 0],
            ['SW7', 'Social Welfare Policies and Programs', 'Social welfare policy, programs, and legislation', 'lecture', 3, 0],
        ];
        foreach ($subjects as [$code, $name, $desc, $type, $lec, $lab]) {
            Subjects::updateOrCreate(
                ['subjectCode' => $code],
                [
                    'subjectName' => $name,
                    'subjectDesc' => $desc,
                    'subjectType' => $type,
                    'lectureUnits' => $lec,
                    'labUnits' => $lab,
                ]
            );
        }

        // ---------- Curriculum per course with per-program major subjects.
        // Every course gets its OWN curriculum: the shared CHED GE/minor
        // pool (plus the CMO 75 s. 2017 Education core for CTE programs)
        // plus program-major subjects drawn from the official CHED
        // curricula (BSIT per UE CIT2019, BSCrim per CMO 5 s. 2018, BSBA-MM
        // per UE BBMM2018, BSHM per UE ABSHM2019, BSTM per CMO 62 s. 2017;
        // the rest follow their CMO PSG). The seeder owns the
        // curriculum-subject content: re-running restores the baseline.
        // Curriculum ROWS are never deleted — enrollments pin curriculumId
        // (refinement item 7). ----------
        $gePool = [
            // code => [yearLevel, semesterOffered, prerequisiteCode]
            'UT1' => [1, '1st', null],
            'TC1' => [1, '1st', null],
            'MM1' => [1, '1st', null],
            'PC1' => [1, '1st', null],
            'PE1' => [1, '1st', null],
            'NSTP1' => [1, '1st', null],
            'RH1' => [1, '2nd', null],
            'ET1' => [1, '2nd', null],
            'PE2' => [1, '2nd', null],
            'NSTP2' => [1, '2nd', null],
            'AA1' => [2, '1st', null],
            'ST1' => [2, '1st', null],
            'PE3' => [2, '1st', null],
            'RZ1' => [2, '2nd', null],
            'PE4' => [2, '2nd', null],
        ];
        $edCore = [
            'ED101' => [1, '1st', null],
            'ED102' => [2, '1st', null],
            'ED103' => [2, '1st', null],
            'ED104' => [2, '2nd', null],
            'ED105' => [2, '2nd', null],
            'ED106' => [2, '2nd', null],
        ];
        $programSubjects = [
            'BSIT' => [
                'IT101' => [1, '1st', null],
                'CCP1101' => [1, '1st', null],
                'CSP1101' => [1, '1st', null],
                'CCP1102' => [1, '2nd', 'CCP1101'],
                'CDS1101' => [1, '2nd', 'CCP1101'],
                'CFD1101' => [1, '2nd', null],
                'CBM1101' => [2, '1st', null],
                'CCP1103' => [2, '1st', 'CCP1102'],
                'CDM1101' => [2, '1st', null],
                'CIM1101' => [2, '1st', 'CFD1101'],
                'CIT2101' => [2, '1st', null],
                'CIT2102' => [2, '1st', null],
                'CDE1101' => [2, '1st', 'CCP1102'],
                'CDT1101' => [2, '2nd', null],
                'CHC1101' => [2, '2nd', null],
                'CIA1101' => [2, '2nd', null],
                'CIP1101' => [2, '2nd', null],
                'CIT2201' => [2, '2nd', null],
                'CSA1101' => [2, '2nd', null],
                'CIP1102' => [2, 'Summer', 'CIP1101'],
                'CIT2301' => [2, 'Summer', null],
            ],
            'BSCrim' => [
                'CRIM101' => [1, '1st', null],
                'CLJ1' => [1, '1st', null],
                'KP1' => [1, '1st', null],
                'CRIM2' => [1, '2nd', 'CRIM101'],
                'GS1' => [1, '2nd', null],
                'GE1' => [1, '2nd', null],
                'LE1' => [2, '1st', null],
                'LEA1' => [2, '1st', null],
                'LEA2' => [2, '1st', 'LEA1'],
                'CDI1' => [2, '1st', null],
                'FR1' => [2, '1st', null],
                'CHEM1' => [2, '2nd', null],
                'CRIM3' => [2, '2nd', 'CRIM2'],
                'CRIM4' => [2, '2nd', null],
                'CA1' => [2, '2nd', null],
                'CDI2' => [2, '2nd', 'CDI1'],
                'LEA3' => [2, '2nd', 'LEA2'],
                'FR2' => [2, '2nd', 'FR1'],
                'CFLM1' => [2, '2nd', null],
            ],
            'BSBA' => [
                'BBC1101' => [1, '1st', null],
                'BBC1109' => [1, '1st', null],
                'BMM1101' => [1, '1st', null],
                'BBC1106' => [1, '2nd', null],
                'BMM1201' => [1, '2nd', 'BMM1101'],
                'BBC1108' => [2, '1st', null],
                'BMM2101' => [2, '1st', 'BMM1101'],
                'BBC1201' => [2, '2nd', null],
                'BBC1202' => [2, '2nd', null],
                'BBC4980' => [2, '2nd', null],
                'BMM2201' => [2, '2nd', 'BMM1101'],
                'BMM2202' => [2, '2nd', 'BMM1101'],
                'ZPD1' => [2, '2nd', null],
            ],
            'BSEd' => [
                'EN1' => [1, '1st', null],
                'FI1' => [1, '1st', null],
                'MA2' => [1, '1st', null],
                'SC1' => [1, '1st', null],
                'SS1' => [1, '1st', null],
            ],
            'BSHM' => [
                'ATH1103' => [1, '1st', null],
                'ATH1110' => [1, '1st', null],
                'AHM1201' => [1, '2nd', null],
                'AHM1202' => [1, '2nd', null],
                'ATH1104' => [1, '2nd', null],
                'ATH1107' => [1, '2nd', null],
                'AHM2101' => [2, '1st', null],
                'AHM2102' => [2, '1st', null],
                'AHE4305' => [2, '1st', null],
                'AHE4302' => [2, '2nd', null],
                'AHE4307' => [2, '2nd', null],
                'AHM2201' => [2, '2nd', null],
                'ATH4301' => [2, '2nd', null],
            ],
            'BSTM' => [
                'ATH1103' => [1, '1st', null],
                'ATH1104' => [1, '2nd', null],
                'ATH1107' => [1, '2nd', null],
                'TM1' => [1, '2nd', null],
                'TM2' => [2, '1st', null],
                'TM3' => [2, '1st', null],
                'TM4' => [2, '2nd', null],
            ],
            'BPA' => [
                'PA1' => [1, '1st', null],
                'PA2' => [1, '1st', null],
                'PA3' => [2, '1st', null],
                'PA4' => [2, '1st', null],
                'PA5' => [2, '2nd', null],
            ],
            'BSAIS' => [
                'AIS1' => [1, '1st', null],
                'AIS2' => [1, '2nd', 'AIS1'],
                'AIS3' => [2, '1st', 'AIS2'],
                'AIS4' => [2, '1st', null],
                'AIS5' => [2, '2nd', null],
            ],
            'BSCE' => [
                'MATH2' => [1, '1st', null],
                'CHEM2' => [1, '1st', null],
                'CE1' => [1, '1st', null],
                'PHYS2' => [1, '2nd', null],
                'CE2' => [1, '2nd', null],
                'CE3' => [2, '1st', 'PHYS2'],
                'CE4' => [2, '1st', null],
                'CE5' => [2, '2nd', 'CE3'],
            ],
            'BSEE' => [
                'MATH2' => [1, '1st', null],
                'CHEM2' => [1, '1st', null],
                'EE1' => [1, '1st', null],
                'PHYS2' => [1, '2nd', null],
                'EE2' => [1, '2nd', null],
                'EE3' => [2, '1st', 'PHYS2'],
                'EE4' => [2, '2nd', 'EE3'],
                'EE5' => [2, '2nd', 'PHYS2'],
            ],
            'BSA' => [
                'AG1' => [1, '1st', null],
                'AG2' => [1, '1st', null],
                'AG3' => [1, '2nd', null],
                'AG4' => [2, '1st', null],
                'AG5' => [2, '1st', null],
                'AG6' => [2, '2nd', 'AG2'],
            ],
            'BSFish' => [
                'FS1' => [1, '1st', null],
                'FS2' => [1, '2nd', null],
                'FS3' => [2, '1st', 'FS1'],
                'FS4' => [2, '1st', null],
                'FS5' => [2, '2nd', 'FS2'],
            ],
            'BEEd' => [
                'BE1' => [2, '1st', null],
                'BE2' => [2, '1st', null],
                'BE3' => [2, '2nd', null],
                'BE4' => [2, '2nd', null],
            ],
            'BECEd' => [
                'EC1' => [1, '1st', null],
                'EC2' => [1, '2nd', null],
                'EC3' => [2, '1st', 'EC2'],
                'EC4' => [2, '2nd', null],
            ],
            'BTLEd' => [
                'BT1' => [1, '1st', null],
                'BT2' => [1, '2nd', null],
                'BT3' => [2, '1st', null],
                'BT4' => [2, '1st', null],
                'BT5' => [2, '2nd', null],
            ],
            'BSSW' => [
                'SW1' => [1, '1st', null],
                'SW2' => [1, '1st', null],
                'SW3' => [1, '2nd', null],
                'SW4' => [2, '1st', null],
                'SW5' => [2, '1st', null],
                'SW6' => [2, '2nd', null],
                'SW7' => [2, '2nd', null],
            ],
        ];
        $subjectIdsByCode = Subjects::pluck('subjectId', 'subjectCode');
        foreach (Courses::all() as $course) {
            $curriculum = Curriculums::firstOrCreate(
                ['courseId' => $course->courseId],
                [
                    'majorId' => null,
                    'effectiveYear' => '2026-06-01',
                    'curriculumName' => "{$course->courseCode} 2026 Curriculum",
                ]
            );
            $rows = $gePool
                + ((int) $course->unitId === 6 ? $edCore : [])
                + ($programSubjects[$course->courseCode] ?? []);
            Curriculumsubjects::where('curriculumId', $curriculum->curriculumId)->delete();
            foreach ($rows as $code => [$yearLevel, $sem, $prereqCode]) {
                if (! $subjectIdsByCode->has($code)) {
                    continue;
                }
                Curriculumsubjects::create([
                    'curriculumId' => $curriculum->curriculumId,
                    'subjectId' => $subjectIdsByCode->get($code),
                    'prerequisiteSubjectId' => $prereqCode ? $subjectIdsByCode->get($prereqCode) : null,
                    'yearLevel' => $yearLevel,
                    'semesterOffered' => $sem,
                ]);
            }
        }

        // ---------- Rooms ----------
        Rooms::firstOrCreate(
            ['roomName' => 'Room 101'],
            ['capacity' => 40, 'building' => 'Main Building']
        );

        // ---------- Fee types ----------
        $fees = [
            ['Tuition Fee (per unit)', 1250.00, FeeUnitBasis::PerUnit],
            ['Miscellaneous Fee', 1500.00, FeeUnitBasis::Flat],
            ['Laboratory Fee', 500.00, FeeUnitBasis::PerUnit],
            ['Library Fee', 250.00, FeeUnitBasis::Flat],
        ];
        foreach ($fees as [$name, $amount, $basis]) {
            Feetypes::firstOrCreate(
                ['feeName' => $name],
                ['defaultAmount' => $amount, 'unitBasis' => $basis->value]
            );
        }

        // ---------- Admission requirements ----------
        $admissionReqs = [
            ['PSA Birth Certificate', 'firstYear', true],
            ['Form 138 / Report Card', 'firstYear', true],
            ['Transfer Credentials / Honorable Dismissal', 'transferee', true],
            ['Certificate of Good Moral Character', 'all', true],
        ];
        foreach ($admissionReqs as [$name, $appliesTo, $required]) {
            Admissionrequirements::firstOrCreate(
                ['requirementName' => $name],
                ['appliesTo' => $appliesTo, 'isRequired' => $required]
            );
        }

        // ---------- Clearance requirements (one row per office; table only has officeId) ----------
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 11, 22] as $officeId) {
            Clearancerequirements::firstOrCreate(['officeId' => $officeId]);
        }

        // ---------- Staff (staff8 = admin for AdminAccessSmoke; office heads) ----------
        Staffusers::firstOrCreate(
            ['username' => 'staff8'],
            [
                'employeeNo' => 'EMP-00008',
                'firstName' => 'System',
                'middleName' => '',
                'lastName' => 'Administrator',
                'email' => 'staff8@seait.edu.ph',
                'passwordHash' => Hash::make('password'),
                'officeId' => 1,
                'contactNo' => '',
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $officeHeadRoles = [
            1 => 'Registrar Head', 2 => 'Accounting Head', 3 => 'Scholarship Head',
            4 => 'Guidance Head', 5 => 'Blocking Head', 6 => 'Admission Head',
            7 => 'Academic Head', 8 => 'Clearance Head', 11 => 'Clinic Head', 22 => 'ID Head',
        ];
        $i = 1;
        foreach ($officeHeadRoles as $officeId => $displayName) {
            $username = "office{$officeId}_head";
            Staffusers::firstOrCreate(
                ['username' => $username],
                [
                    'employeeNo' => 'EMP-'.str_pad((string) (100 + $i), 5, '0', STR_PAD_LEFT),
                    'firstName' => $displayName,
                    'middleName' => '',
                    'lastName' => 'Staff',
                    'email' => "{$username}@seait.edu.ph",
                    'passwordHash' => Hash::make('password'),
                    'officeId' => $officeId,
                    'contactNo' => '',
                    'role' => 'officeHead',
                    'status' => 'active',
                ]
            );
            $i++;
        }

        $this->command?->info(class_basename($this).': done.');
    }
}
