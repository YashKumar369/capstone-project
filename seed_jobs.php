<?php
require_once 'config/db.php';

$jobs = [
    [
        'title' => 'Senior PHP Developer',
        'description' => "We are looking for an experienced PHP Developer to join our team.\n\nResponsibilities:\n- Develop and maintain web applications using PHP and MySQL.\n- Collaborate with frontend developers.\n- Optimize application performance.\n\nRequirements:\n- 5+ years of PHP experience.\n- Strong knowledge of Laravel or Symfony.\n- Experience with RESTful APIs.",
        'location' => 'New York, NY',
        'salary_range' => '$100k - $120k',
        'type' => 'full-time'
    ],
    [
        'title' => 'Frontend Engineer (React)',
        'description' => "Join our dynamic frontend team building modern UIs.\n\nKey Skills:\n- React.js, Redux, HTML5, CSS3.\n- Experience with responsive design.\n\nPerks:\n- Remote work options.\n- Competitive salary.",
        'location' => 'Remote',
        'salary_range' => '$90k - $110k',
        'type' => 'full-time'
    ],
    [
        'title' => 'Marketing Manager',
        'description' => "We need a creative Marketing Manager to lead our campaigns.\n\nDuties:\n- Plan and execute digital marketing strategies.\n- Manage social media accounts.\n- Analyze campaign performance.",
        'location' => 'San Francisco, CA',
        'salary_range' => '$80k - $100k',
        'type' => 'full-time'
    ],
    [
        'title' => 'Freelance Content Writer',
        'description' => "Looking for a talented writer for tech blog posts.\n\nRequirements:\n- Proven experience in technical writing.\n- Ability to meet deadlines.\n- SEO knowledge is a plus.",
        'location' => 'Remote',
        'salary_range' => '$50/hr',
        'type' => 'freelance'
    ],
    [
        'title' => 'DevOps Engineer',
        'description' => "Manage our cloud infrastructure and CI/CD pipelines.\n\nTech Stack:\n- AWS, Docker, Kubernetes, Jenkins.\n- Scripting (Python/Bash).",
        'location' => 'Austin, TX',
        'salary_range' => '$120k - $140k',
        'type' => 'full-time'
    ],
    [
        'title' => 'UX/UI Designer',
        'description' => "Design intuitive and beautiful user experiences.\n\nTools:\n- Figma, Adobe XD.\n- User research and prototyping.",
        'location' => 'London, UK',
        'salary_range' => '£50k - £65k',
        'type' => 'contract'
    ]
];

try {
    // Get a valid employer ID (using the first user found or admin)
    $stmt = $pdo->query("SELECT user_id FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if (!$user) {
        die("Please create at least one user first!");
    }
    
    $employer_id = $user['user_id'];

    foreach ($jobs as $job) {
        $stmt = $pdo->prepare("INSERT INTO jobs (employer_id, title, description, location, salary_range, type, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $employer_id,
            $job['title'],
            $job['description'],
            $job['location'],
            $job['salary_range'],
            $job['type']
        ]);
        echo "Inserted job: " . $job['title'] . "\n";
    }
    
    echo "Jobs seeded successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
