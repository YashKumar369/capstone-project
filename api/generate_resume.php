<?php
header('Content-Type: application/json');
require_once '../config/ai_keys.php';

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$name = $input['fullName'] ?? 'Applicant';
$email = $input['email'] ?? '';
$phone = $input['phone'] ?? '';
$skills = $input['skills'] ?? '';
$experience = $input['experience'] ?? '';
$education = $input['education'] ?? '';
$achievements = $input['achievements'] ?? '';

// Construct Prompt
// Construct Prompt
$prompt = "You are a resume formatter. I will provide you with user data and a specific HTML template. 
Your task is to insert the user's data into the template. 
DO NOT change the layout, CSS, or structure. ONLY replace the square bracket placeholders like [NAME] with the actual data.
If a piece of data is missing, remove that specific placeholder or section gracefully.

**User Data:**
Name: $name
Email: $email
Phone: $phone
Skills: $skills
Experience: $experience
Education: $education
Achievements: $achievements

**The HTML Template (Strictly use this):**
<div id='resume-template' style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; display: flex; flex-direction: row; width: 100%; background: #fff; color: #333;'>
    
    <!-- Left Sidebar -->
    <div style='width: 32%; background-color: #f4f6f8; padding: 30px 20px; border-right: 1px solid #e1e4e8;'>
        <!-- Contact -->
        <div style='margin-bottom: 30px;'>
            <h4 style='color: #4a90e2; text-transform: uppercase; font-size: 14px; border-bottom: 2px solid #4a90e2; padding-bottom: 5px; margin-bottom: 15px;'>Contact</h4>
            <div style='font-size: 13px; line-height: 1.6;'>
                <div style='margin-bottom: 8px;'><strong style='color: #555;'>Email:</strong><br>$email</div>
                <div style='margin-bottom: 8px;'><strong style='color: #555;'>Phone:</strong><br>$phone</div>
            </div>
        </div>

        <!-- Skills -->
        <div style='margin-bottom: 30px;'>
            <h4 style='color: #4a90e2; text-transform: uppercase; font-size: 14px; border-bottom: 2px solid #4a90e2; padding-bottom: 5px; margin-bottom: 15px;'>Skills</h4>
            <div style='display: flex; flex-wrap: wrap; gap: 8px;'>
                <!-- Iterate skills here as badges -->
                [GENERATE_SKILL_BADGES_HERE]
            </div>
        </div>


    </div>

    <!-- Right Content -->
    <div style='width: 68%; padding: 0;'>
        <!-- Header -->
        <div style='background-color: #4a90e2; padding: 40px 30px; color: white;'>
            <h1 style='margin: 0; font-size: 32px; font-weight: 700; line-height: 1.2;'>$name</h1>
            <p style='margin: 10px 0 0; font-size: 18px; opacity: 0.9;'>[INSERT_JOB_TITLE_IF_DETECTED]</p>
        </div>

        <!-- Main Content Body -->
        <div style='padding: 30px;'>
            
            <!-- Profile/Summary -->
            <div style='margin-bottom: 30px;'>
                <h3 style='color: #4a90e2; font-size: 18px; text-transform: uppercase; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;'>Profile</h3>
                <p style='font-size: 14px; line-height: 1.6; color: #555;'>
                    [GENERATE_PROFESSIONAL_SUMMARY_HERE]
                </p>
            </div>

            <!-- Experience -->
            <div>
                <h3 style='color: #4a90e2; font-size: 18px; text-transform: uppercase; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;'>Work Experience</h3>
                <!-- Experience Items -->
                [GENERATE_EXPERIENCE_ITEMS_HERE_WITH_HTML]
            </div>

            <!-- Education -->
            <div style='margin-top: 30px;'>
                <h3 style='color: #4a90e2; font-size: 18px; text-transform: uppercase; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;'>Education</h3>
                [GENERATE_EDUCATION_LIST_HERE]
            </div>

            <!-- Achievements (Optional) -->
             [IF_ACHIEVEMENTS_EXIST_ADD_SECTION_HERE]

        </div>
    </div>
</div>

Instructions for AI (CRITICAL):
1.  **YOU MUST GENERATE CONTENT**: Do not leave any placeholders like [GENERATE...] in the output.
2.  **Summary**: Write a professional 2-3 line summary based on the user's experience and skills. Replace [GENERATE_PROFESSIONAL_SUMMARY_HERE] with this text.
3.  **Skills**: Convert the user's skills into the badge format shown.
4.  **Experience**: Format the provided experience into the HTML structure. Use the user's raw input but format it nicely.
5.  **Education**: Format the education details.
6.  **Job Title**: If the user didn't provide a job title, infer one from their experience (e.g., \"Software Engineer\").
7.  **Final Output**: Return the COMPLETE HTML with all data filled in. Do not output markdown or explanations. Just the HTML.";

// Call OpenAI API
$url = 'https://api.openai.com/v1/chat/completions';
$data = [
    'model' => 'gpt-3.5-turbo', // or gpt-4
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant that generates HTML resumes.'],
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 1500,
    'temperature' => 0.7
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . OPENAI_API_KEY
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'Request failed: ' . curl_error($ch)]);
} else {
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        echo json_encode(['html' => $result['choices'][0]['message']['content']]);
    } else {
        echo json_encode(['error' => 'API Error', 'details' => $result]);
    }
}
curl_close($ch);
?>
