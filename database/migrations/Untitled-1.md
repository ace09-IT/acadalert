# File Tree: acadalerts

**Generated:** 6/12/2026, 8:41:53 PM
**Root Path:** `C:\xampp\htdocs\Capstone2026\acadalerts`  _(historical snapshot — the folder was later renamed from `acadalerts_vibecoding` to `acadalerts`)_

```
├── app
│   ├── Console
│   │   └── Commands
│   ├── Http
│   │   ├── Controllers
│   │   │   ├── Admin
│   │   │   ├── Api
│   │   │   ├── Counselor
│   │   │   ├── MasterTeacher
│   │   │   ├── Student
│   │   │   └── Controller.php
│   │   └── Middleware
│   ├── Jobs
│   ├── Models
│   │   └── User.php
│   ├── Providers
│   │   └── AppServiceProvider.php
│   └── Services
├── bootstrap
│   ├── app.php
│   └── providers.php
├── config
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── services.php
│   └── session.php
├── database
│   ├── csv
│   ├── factories
│   │   └── UserFactory.php
│   ├── migrations
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_06_12_123152_create_departments_table.php
│   │   ├── 2026_06_12_123153_create_programs_table.php
│   │   ├── 2026_06_12_123154_create_year_levels_table.php
│   │   ├── 2026_06_12_123155_create_semesters_table.php
│   │   ├── 2026_06_12_123156_create_blocks_table.php
│   │   ├── 2026_06_12_123157_create_subjects_table.php
│   │   ├── 2026_06_12_123158_create_students_table.php
│   │   ├── 2026_06_12_123159_create_parents_table.php
│   │   ├── 2026_06_12_123200_add_role_to_users_table.php
│   │   ├── 2026_06_12_123201_create_master_teachers_table.php
│   │   ├── 2026_06_12_123202_create_counselors_table.php
│   │   ├── 2026_06_12_123203_create_teacher_assignments_table.php
│   │   ├── 2026_06_12_123204_create_grades_table.php
│   │   ├── 2026_06_12_123205_create_attendance_table.php
│   │   ├── 2026_06_12_123206_create_attendance_summaries_table.php
│   │   ├── 2026_06_12_123207_create_attendance_warnings_table.php
│   │   ├── 2026_06_12_123208_create_risk_scores_table.php
│   │   ├── 2026_06_12_123209_create_flags_table.php
│   │   ├── 2026_06_12_123210_create_intervention_recommendations_table.php
│   │   ├── 2026_06_12_123210_create_risk_thresholds_table.php
│   │   ├── 2026_06_12_123211_create_risk_overrides_table.php
│   │   ├── 2026_06_12_123212_create_cases_table.php
│   │   ├── 2026_06_12_123213_create_escalations_table.php
│   │   ├── 2026_06_12_123214_create_case_sessions_table.php
│   │   ├── 2026_06_12_123215_create_alert_acknowledgments_table.php
│   │   ├── 2026_06_12_123216_create_student_recommendation_tracking_table.php
│   │   ├── 2026_06_12_123217_create_system_settings_table.php
│   │   ├── 2026_06_12_123218_create_school_years_table.php
│   │   ├── 2026_06_12_123219_create_audit_logs_table.php
│   │   ├── 2026_06_12_123220_create_permissions_table.php
│   │   ├── 2026_06_12_123221_create_payments_table.php
│   │   ├── 2026_06_12_123222_create_payment_history_table.php
│   │   └── 2026_06_12_123239_create_academic_calendar_table.php
│   ├── seeders
│   │   └── DatabaseSeeder.php
│   └── .gitignore
├── public
│   ├── js
│   │   └── charts
│   ├── .htaccess
│   ├── favicon.ico
│   ├── index.php
│   └── robots.txt
├── resources
│   └── views
│       ├── admin
│       ├── counselor
│       ├── layouts
│       ├── master-teacher
│       └── student
├── routes
│   ├── console.php
│   └── web.php
├── storage
│   ├── app
│   │   ├── private
│   │   │   └── .gitignore
│   │   ├── public
│   │   │   └── .gitignore
│   │   └── .gitignore
│   ├── framework
│   │   ├── sessions
│   │   │   └── .gitignore
│   │   ├── testing
│   │   │   └── .gitignore
│   │   ├── views
│   │   │   ├── .gitignore
│   │   │   ├── 02adc2d7960086b36b977ab86e848104.php
│   │   │   ├── 084694ab74723b99feece43160c8197d.php
│   │   │   ├── 098d77880c3cb522c8284544cf27e4a5.php
│   │   │   ├── 0a967c76cdc2c9140c0d34b3578054d1.php
│   │   │   ├── 14bbdef228d7a8734e3160fb1e7b9f1a.php
│   │   │   ├── 17fa3190d960aba77599847c59d15014.php
│   │   │   ├── 1dca6587e52502b2465895ec3e6dd661.php
│   │   │   ├── 1e3088fb5a6d096095a91c0f137963a7.php
│   │   │   ├── 29b1efeb23788e8ee331f153f8154aaa.php
│   │   │   ├── 31e30b327df1bfae154b0a140355fc38.php
│   │   │   ├── 3fa9dc361b21836b999e80de850bae5c.php
│   │   │   ├── 470d1261a6f2f924ced07be75760c773.php
│   │   │   ├── 4dbaddfb82bb3cdff8be9f7c4723d67f.php
│   │   │   ├── 4ebb427c5c465adabf39309e9379a32a.php
│   │   │   ├── 4edcac9724b116bdeb8d8622cb123a4e.php
│   │   │   ├── 50f4fa91f8c6ce829295c19032fe7a4f.php
│   │   │   ├── 58c785e1946a57f55e319a820412ed15.php
│   │   │   ├── 6148d104a00c18ad01f9f34e34bbeac5.php
│   │   │   ├── 66a936d6cc501fbe44175fa149ca5b5d.php
│   │   │   ├── 6d48dc529b803d724f0f247c4159ed46.php
│   │   │   ├── 7ad56746e078aac75a9001ffad28da5c.php
│   │   │   ├── 7de891f43cf93f6a5a121a9f4e6872b8.php
│   │   │   ├── 805d910c81df428f7dd8b14b406d4884.php
│   │   │   ├── 8fc3814e528b4fb55b196a5bec556e16.php
│   │   │   ├── 908b310940676332ee98638ee8ba3182.php
│   │   │   ├── 93c71d11f4fa71288ac91cbc45a151bd.php
│   │   │   ├── 99d1c858bf6627aa14f00d4e5ddc1b4e.php
│   │   │   ├── a7103bdfac79c39ee63b643fb59a4919.php
│   │   │   ├── ae28f45c27adf3496fc08969b6c2ebe3.php
│   │   │   ├── ae83b61bb851e7944b3370043ec6ec46.php
│   │   │   ├── b5cc027878c4718a12a66f175550cffe.php
│   │   │   ├── b8d8fc812dd2b0137a9658e449110e9f.php
│   │   │   ├── bb5746aaf3ecb905ad2631c0a77c12f9.php
│   │   │   ├── c35666004fa40236f1941add56b0e581.php
│   │   │   ├── d300c85e4c38569be8a4692a3c5c6b6a.php
│   │   │   ├── d69ba7a33830fbac7cebb86dda3ed113.php
│   │   │   ├── d90e325a14f643b1200e6ec12d3ee855.php
│   │   │   ├── e495357e8285fbd43d3e17a8106e4897.php
│   │   │   ├── e4fe9c993eac731253e1198ef009d2f3.php
│   │   │   ├── e716a84dde6aef22562c884f8ce1c2ec.php
│   │   │   ├── f51b975bc1bebaa7e8f63cf477458348.php
│   │   │   └── f6afb30ab175ef1ad12b9480ea925a38.php
│   │   └── .gitignore
│   └── logs
│       └── .gitignore
├── tests
│   ├── Feature
│   │   └── ExampleTest.php
│   ├── Unit
│   │   └── ExampleTest.php
│   └── TestCase.php
├── .editorconfig
├── .env.example
├── .gitattributes
├── .gitignore
├── README.md
├── Untitled-1.md
├── Untitled-2.md
├── artisan
├── composer.json
└── phpunit.xml
```

---
*Generated by FileTree Pro Extension*