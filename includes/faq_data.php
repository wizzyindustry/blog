<?php
/**
 * FAQ questions and answers, shared by faq.php (all of them) and index.php, the welcome page (the featured ones).
 * Each item: [question, answer HTML, featured?]. Answers are our own trusted HTML; links are
 * relative to the site root (both pages live there).
 */
require_once __DIR__ . "/legal_layout.php";

/** group => [ [question, answer HTML, featured], ... ] */
function faq_groups(): array
{
    $max_mb = format_bytes(user_max_file_bytes());
    $quota = format_bytes(USER_STORAGE_QUOTA);
    
    // group => [ [question, answer HTML], ... ]  (answers use only our own trusted text)
    return [
        'Getting started' => [
            ['What is UDOM Student Materials?', 'A free place for University of Dodoma students to find study materials, lecture notes, past papers and campus news, and to share their own notes and files with each other.'],
            ['Do I need an account?', 'No. Anyone can read the materials, view public shared files and comment. You need a free account to <strong>upload files</strong>, keep a profile, and edit or delete your comments from any device. <a href="account/register.php">Create an account</a>.', true],
            ['Can I sign in with my Google account?', 'Yes, when the button is shown. Choose <strong>Continue with Google</strong> on the sign-in or sign-up page. The first time, you confirm your name and username and accept the Terms. If you already have an account with the same email, sign in with your password first, then open <a href="account/security.php">Password &amp; Security</a> and choose <strong>Connect Google</strong>. We never see your Google password and cannot access your Gmail or Drive.'],
            ['I forgot my password. What do I do?', 'On the sign-in page choose <strong>Forgot password?</strong> and enter the email address of your account. We email you a link to choose a new password. The link works once and expires after 30 minutes, and after the reset you are signed out on every other device. No email? Check your spam folder, or contact ' . site_contact_html() . '.'],
            ['How do I change my name, email or photo?', 'Sign in, open the profile menu at the top-right and choose <strong>My Profile</strong>.'],
        ],
        'Uploading and sharing files' => [
            ['What kinds of files can I upload?', 'PDF, Word (DOC, DOCX, ODT, RTF), PowerPoint (PPT, PPTX, ODP, PPSX), Excel (XLS, XLSX, ODS, CSV), images (JPG, PNG, GIF, WEBP, BMP, HEIC, AVIF), videos (MP4, WEBM, MOV, AVI, MKV...), audio (MP3, WAV, OGG, M4A...), ZIP/RAR/7Z archives and plain text notes (TXT, MD). For safety, programs, scripts, web pages and SVG images are not accepted.', true],
            ['How big can my files be?', 'Up to <strong>' . $max_mb . '</strong> per file, up to ' . USER_MAX_FILES_PER_UPLOAD . ' files at a time, and <strong>' . $quota . '</strong> of storage per account. Your storage use is shown in the sidebar.', true],
            ['Why was my file rejected?', 'The site checks what is <em>inside</em> a file, not just its name. A file is refused if it does not match the type you chose (for example a picture uploaded as "PDF"), if it is damaged, if it is an Office file with macros, or if it contains program code. Choose the correct type on the upload page and try again.'],
            ['Who can see my files?', 'You choose for every file. <strong>Private</strong> files are visible only to you. <strong>Public</strong> files appear on the Materials and Shared Files pages, and anyone can view and download them. You can switch between the two at any time in <strong>My Files</strong>.', true],
            ['Why are my images smaller after uploading?', 'Large images are automatically resized and compressed before upload, so they upload faster, use less of your storage and load quickly for others. This also removes hidden location (GPS) data from photos. Text and older Office files are also compressed on the server and restored automatically when downloaded.'],
            ['Can I share textbooks or exam papers?', 'Only share your own work or material you have permission to share. Copyrighted books and leaked or unreleased exam papers are not allowed. See the <a href="terms.php">Terms &amp; Conditions</a>.', true],
        ],
        'Comments' => [
            ['Can I reply to someone\'s comment?', 'Yes. Click <strong>Reply</strong> under any comment. Anyone can reply, including to replies.'],
            ['Can I edit or delete my comment?', 'Yes, but only your own. If you were signed in when you commented, you can edit or delete it at any time from any device. Guest comments can only be changed from the same browser session they were posted from.'],
            ['Why does a comment say "This comment was deleted"?', 'When someone deletes a comment that already has replies, the text is removed but the replies stay, so other people\'s answers are not lost.'],
        ],
        'Privacy and security' => [
            ['What data do you keep about me?', 'Only what the site needs: your account details, your files and comments, and basic security logs. We never sell data or show ads. Read the <a href="privacy.php">Privacy Policy</a>.', true],
            ['What cookies do you use?', 'Only two necessary cookies: one keeps you signed in, one remembers your cookie choice. Google Fonts loads only if you accept it. See the <a href="cookies.php">Cookie Policy</a>.'],
            ['Why am I locked out after typing the wrong password?', 'To stop people guessing passwords, sign-in pauses for 15 minutes after several failed attempts. Wait and try again with the correct password.'],
            ['How do I delete my account?', 'Sign in and go to <a href="account/security.php">Password &amp; Security</a> &rarr; <strong>Delete Account</strong>. This permanently deletes your files.'],
            ['How do I report a file or comment?', 'Contact ' . site_contact_html() . ' with a link to it. The administrator can remove content that breaks the rules.'],
        ],
    ];
}

/** The questions marked as featured (shown on the landing page), in order. */
function faq_featured(): array
{
    $out = [];
    foreach (faq_groups() as $items) {
        foreach ($items as $item) {
            if (!empty($item[2])) {
                $out[] = $item;
            }
        }
    }
    return $out;
}
