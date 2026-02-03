<?php
require_once 'config/db.php';

try {
    // 1. Clear existing jobs
    echo "Clearing existing jobs...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE jobs");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Jobs table cleared.\n";

    // 2. Fetch Existing Employers
    $stmt = $pdo->query("SELECT user_id, username FROM users WHERE user_type = 'employer'");
    $employers = $stmt->fetchAll();

    if (empty($employers)) {
        echo "No users with type 'employer' found. Checking for any users...\n";
        $stmt = $pdo->query("SELECT user_id, username FROM users LIMIT 5");
        $employers = $stmt->fetchAll();
    }

    if (empty($employers)) {
        die("Error: No users found in the database. Please create users first.\n");
    }

    echo "Found " . count($employers) . " potential employers/posters.\n";

    // 3. Define 20 Unique Jobs with Varied Formats
    $jobs_data = [
        [
            'title' => 'Senior Software Engineer',
            'description' => "We are looking for a Senior Software Engineer to help us build the next generation of our platform.

            **The Role**
            You will be responsible for designing and implementing scalable backend services. You'll work with a team of talented engineers to solve complex problems and deliver high-quality software. This is a hands-on role where you will be writing code, reviewing pull requests, and mentoring junior developers.
            
            **About You**
            You have deep experience with PHP and modern frameworks like Laravel or Symfony. You understand the importance of testing and documentation. You are passionate about writing clean, maintainable code. You are comfortable working in an Agile environment and delivering features iteratively.
            
            **Tech Stack**
            * PHP 8.1+
            * MySQL / PostgreSQL
            * Docker & Kubernetes
            * AWS (EC2, RDS, S3)
            
            **What We Offer**
            We offer a competitive salary, equity, and a comprehensive benefits package. We are a remote-first company with flexible working hours. We believe in work-life balance and encourage our employees to take ownership of their time. If you are looking for a challenging and rewarding role, apply today!",
            'salary' => '$130k - $160k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Product Manager',
            'description' => "Join our growing team as a Product Manager!
            
            We are seeking a visionary Product Manager to lead our B2B SaaS product. You will be the voice of the customer, translating their needs into actionable requirements for our engineering team.
            
            **Key Responsibilities:**
            1.  **Product Strategy:** Define and execute the product roadmap.
            2.  **Market Research:** Analyze competitor offerings and market trends.
            3.  **Stakeholder Management:** Collaborate with sales, marketing, and support teams to ensure product success.
            4.  **Data Analysis:** Use metrics to drive decision-making and measure product performance.
            
            **Requirements:**
            - 3+ years of product management experience.
            - Strong analytical and problem-solving skills.
            - Excellent communication and leadership abilities.
            - Experience with Agile methodologies (Scrum/Kanban).
            
            **Why Us?**
            We are a fast-growing startup with a culture of innovation. We value diversity and inclusion. Join us and make a real impact on the industry.",
            'salary' => '$110k - $140k',
            'type' => 'full-time'
        ],
        [
            'title' => 'UX Designer',
            'description' => "Are you obsessed with user experience? Do you love solving design challenges?
            
            We are looking for a creative UX Designer to join our design team. You will be responsible for creating intuitive and beautiful user interfaces for our web and mobile applications.
            
            **What You'll Do:**
            - Conduct user research and usability testing.
            - Create wireframes, prototypes, and high-fidelity mockups.
            - Collaborate with developers to ensure design intent is maintained in production.
            - Maintain and evolve our design system.
            
            **Skills We Look For:**
            - Proficiency in Figma, Sketch, or Adobe XD.
            - Strong understanding of user-centered design principles.
            - Ability to communicate design decisions effectively.
            - A portfolio demonstrating your design process and problem-solving skills.
            
            This is a remote opportunity. We offer a stipend for your home office setup and a learning budget for your professional development.",
            'salary' => '$90k - $120k',
            'type' => 'contract'
        ],
        [
            'title' => 'Data Scientist',
            'description' => "Data Scientist Needed for FinTech Leader!
            
            **Job Reference:** DS-2024-NY
            
            **Overview:**
            Our client, a leading FinTech company, is looking for a Data Scientist to join their Analytics Center of Excellence. You will build machine learning models to detect fraud, optimize credit scoring, and personalize user experiences.
            
            **Primary Duties:**
            *   Process and analyze terabytes of transaction data using Spark and Python.
            *   Develop predictive models using XGBoost, TensorFlow, or PyTorch.
            *   Collaborate with engineering teams to deploy models into production.
            *   Present findings to executive leadership using data visualization tools like Tableau or Looker.
            
            **Qualifications:**
            *   Masters or PhD in Statistics, Computer Science, or related field.
            *   Strong SQL and Python programming skills.
            *   Experience with cloud platforms is a plus (AWS/GCP).
            
            **Benefits:**
            *   Competitive base salary + bonus.
            *   401(k) matching.
            *   Health, dental, and vision insurance.
            *   Gym membership reimbursement.",
            'salary' => '$140k - $180k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Marketing Specialist',
            'description' => "We are hiring a Marketing Specialist!
            
            **The Role:**
            As our Marketing Specialist, you will be the creative force behind our brand's voice. You will manage our social media presence, create engaging content, and run digital advertising campaigns.
            
            **Your Day-to-Day:**
            Monday: Plan social media content for the week.
            Tuesday: Analyze campaign performance and adjust ad spend.
            Wednesday: Write blog posts and newsletters.
            Thursday: Collaborate with the design team on visuals.
            Friday: Report on key metrics and brainstorm new ideas.
            
            **What You Bring:**
            - Creativity and enthusiasm.
            - Experience with Facebook Ads, Google Ads, and LinkedIn Marketing.
            - Strong copywriting skills.
            - A data-driven mindset.
            
            **Perks:**
            - Unlimited PTO.
            - Dog-friendly office.
            - Weekly team lunches.
            - Annual company retreat.",
            'salary' => '$60k - $80k',
            'type' => 'part-time'
        ],
        [
            'title' => 'Sales Representative',
            'description' => "Hungry for success? Join our High-Velocity Sales Team!
            
            We are looking for a results-driven Sales Representative to drive new business. If you are tenacious, resilient, and love the thrill of the close, we want to talk to you.
            
            **Responsibilities:**
            > Prospect and qualify leads.
            > Conduct product demos and presentations.
            > Negotiate contracts and close deals.
            > Maintain accurate records in Salesforce.
            
            **Requirements:**
            - Proven track record of exceeding sales quotas.
            - Excellent phone and email communication skills.
            - Ability to handle rejection and keep moving forward.
            - Strong organizational skills.
            
            **Commission Structure:**
            Uncapped commission! Top performers earn $200k+.
            Base salary provided.",
            'salary' => '$50k Base + Comm',
            'type' => 'full-time'
        ],
        [
            'title' => 'Customer Support Lead',
            'description' => "**Customer Support Team Lead**
            
            *Location: Remote / Austin, TX*
            
            **Mission:**
            To deliver world-class support to our enterprise customers and lead a team of support agents to success.
            
            **Responsibilities:**
            - Monitor ticket queues and ensure SLAs are met.
            - Handle escalated issues and complex technical inquiries.
            - Train and mentor new support team members.
            - Identify trends in customer issues and provide feedback to the product team.
            
            **Ideal Candidate:**
            You have 5+ years of customer support experience, with at least 2 years in a leadership role. You are patient, empathetic, and a great communicator. You have experience with Zendesk and JIRA.
            
            **Our Culture:**
            We maximize customer happiness. We believe in transparency and autonomy.",
            'salary' => '$70k - $90k',
            'type' => 'full-time'
        ],
        [
            'title' => 'DevOps Engineer',
            'description' => "DevOps Engineer - Infrastructure as Code
            
            **Summary:**
            We are automating everything. From server provisioning to application deployment, we want it all in code. We are looking for a DevOps Engineer to help us achieve this goal.
            
            **Tech Stack:**
            Linux, AWS, Terraform, Ansible, Docker, Kubernetes, GitLab CI.
            
            **What you will be doing:**
            1. Improving our CI/CD pipelines to reduce build times.
            2. Managing our Kubernetes clusters.
            3. Implementing security best practices.
            4. Monitoring system performance and reliability.
            
            **Requirements:**
            - Strong scripting skills (Bash/Python).
            - Experience with cloud-native technologies.
            - Understanding of networking fundamentals.
            
            **Apply if:**
            You love automating boring tasks. You hate manual deployments. You want to work with smart people on interesting problems.",
            'salary' => '$120k - $150k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Mobile App Developer',
            'description' => "**iOS / Android Developer (React Native)**
            
            We are building a mobile app that helps people discover local events. We need a Mobile App Developer to take ownership of the mobile experience.
            
            **The Job:**
            - develop cross-platform mobile apps using React Native.
            - integrate with REST APIs and third-party services.
            - optimize app performance for smooth 60fps animations.
            - publish apps to the App Store and Google Play.
            
            **Must Haves:**
            * Issued at least one app on the App Store.
            * Solid understanding of JavaScript/TypeScript.
            * Experience with native modules is a plus.
            
            **Nice to Haves:**
            * Design skills.
            * Backend experience.
            
            Join us and help millions of users find fun things to do!",
            'salary' => '$100k - $130k',
            'type' => 'freelance'
        ],
        [
            'title' => 'Content Writer',
            'description' => "Freelance Content Writer Needed
            
            We are a digital marketing agency looking for a talented writer to create blog posts, whitepapers, and case studies for our B2B clients.
            
            **Scope of Work:**
            - Researching complex topics in technology and finance.
            - Writing clear, concise, and compelling copy.
            - Adhering to SEO best practices.
            - Adapting tone and style for different clients.
            
            **Requirements:**
            - Native-level English proficiency.
            - Strong portfolio of published work.
            - Ability to meet strict deadlines.
            - Familiarity with WordPress.
            
            **Rate:**
            $0.10 - $0.20 per word, depending on experience.
            
            **To Apply:**
            Please send 3 samples of your writing and your resume.",
            'salary' => 'Competitive Rates',
            'type' => 'freelance'
        ],
        [
            'title' => 'HR Manager',
            'description' => "Human Resources Manager
            
            **Company Overview:**
            We are a logistics company with over 500 employees. We are looking for an HR Manager to oversee our HR department.
            
            **Duties:**
            * Manage the recruitment process.
            * Administer benefits and payroll.
            * Handle employee relations and conflict resolution.
            * Ensure compliance with labor laws.
            
            **Qualifications:**
            * Bachelor's degree in HR or related field.
            * SHRM-CP or PHR certification preferred.
            * 5+ years of HR experience.
            
            **What we offer:**
            A stable work environment, career advancement opportunities, and a supportive leadership team.",
            'salary' => '$85k - $105k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Business Analyst',
            'description' => "**Role:** Business Analyst
            **Department:** IT / Project Management
            
            **Description:**
            The Business Analyst will act as a bridge between business stakeholders and the technical team. You will gather requirements, document processes, and ensure that our software solutions meet business needs.
            
            **Core Competencies:**
            - Requirements Elicitation
            - Process Modeling (BPMN)
            - Data Analysis (SQL, Excel)
            - Stakeholder Communication
            
            **Experience:**
            - 3-5 years as a BA in a software development environment.
            - Experience with Waterfall and Agile methodologies.
            
            **Location:**
            Hybrid (3 days in office, 2 days remote). London, UK.",
            'salary' => '£55k - £70k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Project Manager',
            'description' => "Senior Project Manager (Construction / Engineering)
            
            We are looking for an experienced Project Manager to lead large-scale infrastructure projects.
            
            **Responsibilities:**
            - Plan and schedule project timelines.
            - Manage budgets and resources.
            - Coordinate with architects, engineers, and subcontractors.
            - Ensure safety and quality standards are met.
            
            **Requirements:**
            - PMP certification.
            - Degree in Civil Engineering or Construction Management.
            - 10+ years of industry experience.
            
            **Benefits:**
            Company car, health insurance, pension plan.",
            'salary' => '$110k - $140k',
            'type' => 'contract'
        ],
        [
            'title' => 'Full Stack Developer',
            'description' => "Full Stack Developer (MERN Stack)
            
            **Hey there!**
            We're a small but mighty team building a platform to help creators monetize their work. We're looking for a Full Stack Dev who can wear many hats.
            
            **The Tech:**
            MongoDB, Express.js, React, Node.js.
            We also use Redis, Elasticsearch, and RabbitMQ.
            
            **You should be good at:**
            - Building RESTful APIs.
            - Creating responsive front-end components.
            - Debugging performance issues.
            - Working independently.
            
            **Why you'll love it here:**
            - No bureaucracy.
            - Ship code to production on day one.
            - Work from anywhere in the world.
            - Competitive pay.",
            'salary' => '$100k - $130k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Cloud Architect',
            'description' => "**Cloud Solution Architect**
            
            **Overview:**
            Design secure, scalable, and reliable cloud solutions for our enterprise clients. You will be the technical authority on cloud architecture.
            
            **Responsibilities:**
            * Lead migration projects from on-premise to cloud (Azure/AWS).
            * Design cloud-native applications using microservices.
            * Conduct architectural reviews.
            * Mentor engineering teams on cloud best practices.
            
            **Skills:**
            * Deep knowledge of AWS or Azure.
            * Experience with IaC (Terraform/CloudFormation).
            * Strong understanding of security paradigms.
            
            **Certifications:**
            AWS Certified Solutions Architect Professional or Azure Solutions Architect Expert required.",
            'salary' => '$160k - $200k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Database Administrator',
            'description' => "Database Administrator (Oracle/PostgreSQL)
            
            **Job Description:**
            We are resolving complex data challenges. We need a DBA to ensure our databases are performant, secure, and available.
            
            **Tasks:**
            - Monitor database performance and tune SQL queries.
            - Manage backups and disaster recovery plans.
            - Apply patches and upgrades.
            - Assist developers with schema design.
            
            **Environment:**
            High-availability, 24/7 critical systems options.
            
            **Required Experience:**
            - 5+ years of DBA experience.
            - Strong knowledge of Linux shell scripting.
            - Experience with replication and clustering.",
            'salary' => '$100k - $125k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Network Engineer',
            'description' => "Network Engineer
            
            **Summary:**
            Maintain and support our global network infrastructure. Routers, switches, firewalls, VPNs - you name it.
            
            **Key Duties:**
            - Configure and install network hardware (Cisco/Juniper).
            - Troubleshoot network connectivity issues.
            - Monitor network usage and security.
            - Implement network policies.
            
            **Must-haves:**
            - CCNA or CCNP certification.
            - Experience with BGP, OSPF, VLANs.
            - Ability to work on-call rotations.
            
            **Location:**
            On-site in Chicago, IL.",
            'salary' => '$80k - $110k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Cyber Security Analyst',
            'description' => "**Security Operations Center (SOC) Analyst**
            
            **Defend expectations.**
            You are the first line of defense against cyber threats. You will monitor security alerts and investigate incidents.
            
            **What you'll do:**
            - Analyze logs from SIEM tools (Splunk, QRadar).
            - Investigate phishing emails and malware alerts.
            - Perform vulnerability scans.
            - Participate in incident response activities.
            
            **Skills:**
            - Knowledge of TCP/IP networking.
            - Familiarity with Windows and Linux internals.
            - scripting skills (Python/Powershell) are a plus.
            
            **Shift:**
            This is a shift-based role (includes nights/weekends). Shift differential pay included.",
            'salary' => '$75k - $95k',
            'type' => 'full-time'
        ],
        [
            'title' => 'QA Engineer',
            'description' => "Quality Assurance Engineer (Automation)
            
            **Role:**
            Ensure our software is bug-free and rock-solid. We are moving from manual testing to automation and need your help.
            
            **Responsibilities:**
            - Design and develop automation frameworks (Selenium/Cypress).
            - Write test plans and test cases.
            - Execute regression tests.
            - Report and track defects in Jira.
            
            **Requirements:**
            - 3+ years of QA experience.
            - Coding skills in Java or JavaScript.
            - Experience with CI/CD tools.
            
            **Values:**
            Quality is everyone's responsibility.",
            'salary' => '$85k - $110k',
            'type' => 'full-time'
        ],
        [
            'title' => 'Technical Writer',
            'description' => "Technical Writer - Developer Documentation
            
            **We build tools for developers.**
            Our documentation is our product. We need a Technical Writer who can explain complex concepts simply.
            
            **What you'll write:**
            - API references.
            - Getting started guides.
            - Tutorials and code samples.
            - Release notes.
            
            **You are:**
            - A developer who loves writing OR a writer who code.
            - Detail-oriented.
            - A fast learner.
            
            **Tools:**
            Markdown, Git, Static Site Generators (Jekyll/Hugo).",
            'salary' => '$80k - $100k',
            'type' => 'contract'
        ]
    ];

    $locations = ['New York, NY', 'San Francisco, CA', 'Austin, TX', 'London, UK', 'Remote', 'Sydney, AU', 'Berlin, DE', 'Toronto, CA'];

    // 4. Generate 20 Jobs
    echo "Seeding 20 jobs with unique descriptions...\n";
    $count = 0;
    
    foreach ($jobs_data as $job) {
        $employer = $employers[array_rand($employers)];
        $location = $locations[array_rand($locations)];

        $stmt = $pdo->prepare("INSERT INTO jobs (employer_id, title, description, location, salary_range, type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->execute([
            $employer['user_id'],
            $job['title'],
            $job['description'],
            $location,
            $job['salary'],
            $job['type']
        ]);
        $count++;
    }

    echo "Successfully seeded $count jobs with DISTINCT unique descriptions.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
