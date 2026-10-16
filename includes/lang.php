<?php
/**
 * Internationalization & Localization (English & Malayalam)
 */

require_once __DIR__ . '/../config.php';

// Handle language selection toggle
if (isset($_GET['lang'])) {
    $requested_lang = strtolower(trim($_GET['lang']));
    if (in_array($requested_lang, ['en', 'ml'])) {
        $_SESSION['app_language'] = $requested_lang;
    }
}

function current_lang() {
    return isset($_SESSION['app_language']) ? $_SESSION['app_language'] : 'en';
}

function set_lang($lang) {
    if (in_array($lang, ['en', 'ml'])) {
        $_SESSION['app_language'] = $lang;
    }
}

// Translations Dictionary
$TRANSLATIONS = [
    'en' => [
        'app_name' => 'CivicLens',
        'tagline' => 'Report. Track. Resolve.',
        'welcome_title' => 'Empowering Communities, Resolving Civic Issues',
        'welcome_subtitle' => 'Select your portal to continue. Report community issues or access administrative management.',
        'citizen_portal' => 'Citizen Portal',
        'citizen_portal_desc' => 'File civic complaints, submit anonymous tips, track resolution status, and provide feedback.',
        'enter_citizen_portal' => 'Enter Citizen Portal',
        'admin_portal' => 'Administrator Access',
        'admin_portal_desc' => 'Secure portal for municipal officers and administrators to manage, assign, and verify civic reports.',
        'admin_login_btn' => 'Admin Login',
        'authority_portal' => 'Authority Portal',
        'authority_desc' => 'Department officials login to take action and resolve assigned complaints.',
        'authority_login_btn' => 'Authority Login',
        'business_portal' => 'Local Business Hub',
        'business_desc' => 'Promote your local services with verified digital marketing packages.',
        'business_login_btn' => 'Business Portal',
        'login' => 'Login',
        'register' => 'Register',
        'logout' => 'Logout',
        'dashboard' => 'Dashboard',
        'report_issue' => 'Report an Issue',
        'anonymous_complaint' => 'Anonymous Complaint',
        'track_complaint' => 'Track Complaint',
        'notifications' => 'Notifications',
        'satisfaction_feedback' => 'Satisfaction Feedback',
        'my_complaints' => 'My Complaints',
        'category' => 'Category',
        'title' => 'Title',
        'description' => 'Description',
        'location' => 'Location / Landmark',
        'photo' => 'Photograph (Optional)',
        'status' => 'Status',
        'date_submitted' => 'Date Submitted',
        'resolution_notes' => 'Resolution Notes',
        'status_history' => 'Status History',
        'actions' => 'Actions',
        'view_details' => 'View Details',
        'submit' => 'Submit',
        'cancel' => 'Cancel',
        'back' => 'Back',
        'sponsored' => 'Sponsored',
        'advertisement' => 'Advertisement',
        'discover_local' => 'Discover Local Businesses Near You',
        'satisfied' => 'Satisfied — Close Complaint',
        'not_satisfied' => 'Not Satisfied — Reopen Complaint',
        
        // Categories
        'cat_water' => 'Water',
        'cat_road' => 'Road',
        'cat_food' => 'Food',
        'cat_waste' => 'Waste',
        'cat_thoofan' => 'Drugs / Substance Abuse (Thoofan)',
        'cat_other' => 'Other Issues',
        
        // Statuses
        'status_opened' => 'Opened',
        'status_inprogress' => 'In Progress',
        'status_solved' => 'Solved',
        'status_reopened' => 'Reopened',
        'status_closed' => 'Closed',
        
        'lang_en' => 'English',
        'lang_ml' => 'മലയാളം (Malayalam)',
    ],
    'ml' => [
        'app_name' => 'സിവിക് ലെൻസ്',
        'tagline' => 'റിപ്പോർട്ട് ചെയ്യുക. ട്രാക്ക് ചെയ്യുക. പരിഹരിക്കുക.',
        'welcome_title' => 'ജനസമൂഹത്തെ ശാക്തീകരിക്കുന്നു, ജനകീയ പ്രശ്നങ്ങൾ പരിഹരിക്കുന്നു',
        'welcome_subtitle' => 'തുടരുന്നതിന് നിങ്ങളുടെ പോർട്ടൽ തിരഞ്ഞെടുക്കുക. പരാതികൾ സമർപ്പിക്കുക അല്ലെങ്കിൽ അഡ്മിനിസ്ട്രേറ്റീവ് സംവിധാനം ഉപയോഗിക്കുക.',
        'citizen_portal' => 'പൗരന്മാരുടെ പോർട്ടൽ',
        'citizen_portal_desc' => 'പരാതികൾ സമർപ്പിക്കുക, അജ്ഞാത പരാതികൾ നൽകുക, സ്ഥിതിവിവരം അറിയുക, പ്രതികരണം അറിയിക്കുക.',
        'enter_citizen_portal' => 'പൗര പോർട്ടലിലേക്ക് കടക്കുക',
        'admin_portal' => 'അഡ്മിനിസ്ട്രേറ്റർ പ്രവേശനം',
        'admin_portal_desc' => 'പരാതികൾ പരിശോധിക്കാനും ബന്ധപ്പെട്ട വകുപ്പുകൾക്ക് കൈമാറാനും അഡ്മിൻ ലോഗിൻ.',
        'admin_login_btn' => 'അഡ്മിൻ ലോഗിൻ',
        'authority_portal' => 'ഉദ്യോഗസ്ഥ പോർട്ടൽ',
        'authority_desc' => 'വകുപ്പ് ഉദ്യോഗസ്ഥർക്ക് ലഭിച്ച പരാതികൾ പരിശോധിക്കാനും നടപടി സ്വീകരിക്കാനും.',
        'authority_login_btn' => 'ഉദ്യോഗസ്ഥ ലോഗിൻ',
        'business_portal' => 'പ്രാദേശിക വ്യാപാര വേദി',
        'business_desc' => 'നിങ്ങളുടെ സ്ഥാപനങ്ങളുടെ പരസ്യങ്ങൾ നിയമാനുസൃതമായി നൽകുക.',
        'business_login_btn' => 'ബിസിനസ്സ് പോർട്ടൽ',
        'login' => 'ലോഗിൻ',
        'register' => 'രജിസ്ട്രേഷൻ',
        'logout' => 'ലോഗ് ഔട്ട്',
        'dashboard' => 'ഡാഷ്‌ബോർഡ്',
        'report_issue' => 'പരാതി സമർപ്പിക്കുക',
        'anonymous_complaint' => 'രഹസ്യ / അജ്ഞാത പരാതി',
        'track_complaint' => 'പരാതി ട്രാക്ക് ചെയ്യുക',
        'notifications' => 'അറിയിപ്പുകൾ',
        'satisfaction_feedback' => 'തൃപ്തികരമായ പ്രതികരണം',
        'my_complaints' => 'എന്റെ പരാതികൾ',
        'category' => 'വിഭാഗം',
        'title' => 'ശീർഷകം',
        'description' => 'വിവരണം',
        'location' => 'സ്ഥലം / അടയാളം',
        'photo' => 'ഫോട്ടോ (നിർബന്ധമില്ല)',
        'status' => 'സ്ഥിതിവിവരം',
        'date_submitted' => 'സമർപ്പിച്ച തീയതി',
        'resolution_notes' => 'പരിഹാര കുറിപ്പുകൾ',
        'status_history' => 'മുൻകാല വിവരങ്ങൾ',
        'actions' => 'നടപടികൾ',
        'view_details' => 'വിശദാംശങ്ങൾ കാണുക',
        'submit' => 'സമർപ്പിക്കുക',
        'cancel' => 'റദ്ദാക്കുക',
        'back' => 'പിന്നോട്ട്',
        'sponsored' => 'പരസ്യം',
        'advertisement' => 'പരസ്യം',
        'discover_local' => 'നിങ്ങളുടെ അടുത്തുള്ള പ്രാദേശിക സ്ഥാപനങ്ങൾ',
        'satisfied' => 'തൃപ്തികരം — പരാതി അവസാനിപ്പിക്കുക',
        'not_satisfied' => 'തൃപ്തികരമല്ല — വീണ്ടും പരിഗണിക്കുക',
        
        // Categories
        'cat_water' => 'ജലം',
        'cat_road' => 'റോഡ്',
        'cat_food' => 'ഭക്ഷണം',
        'cat_waste' => 'മാലിന്യം',
        'cat_thoofan' => 'ലഹരി വിരുദ്ധം (തൂഫാൻ)',
        'cat_other' => 'മറ്റ് പ്രശ്നങ്ങൾ',
        
        // Statuses
        'status_opened' => 'തുറന്നത് (Opened)',
        'status_inprogress' => 'പുരോഗതിയിൽ (In Progress)',
        'status_solved' => 'പരിഹരിച്ചത് (Solved)',
        'status_reopened' => 'വീണ്ടും തുറന്നത് (Reopened)',
        'status_closed' => 'പൂർത്തിയായത് (Closed)',
        
        'lang_en' => 'English',
        'lang_ml' => 'മലയാളം',
    ]
];

function __($key) {
    global $TRANSLATIONS;
    $lang = current_lang();
    if (isset($TRANSLATIONS[$lang][$key])) {
        return $TRANSLATIONS[$lang][$key];
    }
    if (isset($TRANSLATIONS['en'][$key])) {
        return $TRANSLATIONS['en'][$key];
    }
    return $key;
}
