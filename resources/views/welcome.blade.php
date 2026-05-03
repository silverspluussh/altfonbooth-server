<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Altfon Booth | Connectivity Redefined</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0066FF;
            --primary-dark: #0052CC;
            --black: #000000;
            --white: #FFFFFF;
            --gray-light: #F8F9FA;
            --gray: #6C757D;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--white);
            color: var(--black);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Navbar */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--black);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo span {
            color: var(--primary);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--black);
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .btn-login {
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-outline {
            border: 2px solid var(--black);
            color: var(--black);
        }

        .btn-outline:hover {
            background: var(--black);
            color: var(--white);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            border: 2px solid var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 102, 255, 0.2);
        }

        /* Hero Section */
        .hero {
            padding: 10rem 5% 5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            min-h-screen;
        }

        .hero-content h1 {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 2.5rem;
            max-width: 500px;
        }

        .hero-btns {
            display: flex;
            gap: 1rem;
        }

        .hero-image {
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        .hero-image img {
            width: 100%;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        /* Features Section */
        .features, .pricing {
            padding: 8rem 5%;
            background: var(--gray-light);
        }

        .section-header {
            text-align: center;
            margin-bottom: 5rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .feature-card {
            background: var(--white);
            padding: 3rem 2rem;
            border-radius: 24px;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(0, 102, 255, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: var(--gray);
            font-size: 1rem;
        }

        /* Footer */
        footer {
            padding: 5rem 5% 2rem;
            background: var(--black);
            color: var(--white);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 4rem;
            margin-bottom: 4rem;
        }

        .footer-brand h2 {
            margin-bottom: 1rem;
        }

        .footer-brand p {
            color: #999;
            max-width: 300px;
        }

        .footer-col h4 {
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 0.8rem;
        }

        .footer-col ul li a {
            color: #999;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-col ul li a:hover {
            color: var(--white);
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 2rem;
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
                padding-top: 8rem;
            }

            .hero-content h1 {
                font-size: 3rem;
            }

            .hero-content p {
                margin-inline: auto;
            }

            .hero-btns {
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .nav-links {
                display: none;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav>
        <a href="#" class="logo">Altfon<span>booth</span></a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="#">Resources</a>
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn-login btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn-login btn-outline">Sign In</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn-login btn-primary">Join Now</a>
                    @endif
                @endauth
            @endif
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Stay Connected <br><span>Anywhere.</span></h1>
            <p>Experience the future of telecommunications with Altfon Booth. Reliable, secure, and built for the modern world.</p>
            <div class="hero-btns">
                <a href="{{ route('register') }}" class="btn-login btn-primary" style="padding: 1rem 2.5rem; font-size: 1rem;">Get Started</a>
                <a href="#features" class="btn-login btn-outline" style="padding: 1rem 2.5rem; font-size: 1rem;">Learn More</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="{{ asset('images/hero.png') }}" alt="Altfon Booth Telecommunications">
        </div>
    </section>

    <section class="features" id="features">
        <div class="section-header">
            <h2>Why Choose Altfon Booth?</h2>
            <p>Powerful features designed to keep you ahead.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Secure Calling</h3>
                <p>End-to-end encrypted communication ensuring your conversations remain private and protected.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🌍</div>
                <h3>Global Reach</h3>
                <p>Connect with anyone, anywhere in the world with our optimized global network infrastructure.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Instant Setup</h3>
                <p>Get up and running in minutes with our streamlined onboarding and intuitive dashboard.</p>
            </div>
        </div>
    </section>

    <section class="pricing" id="pricing">
        <div class="section-header">
            <h2>Simple, Transparent Pricing</h2>
            <p>Choose the plan that fits your connectivity needs.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card" style="text-align: center;">
                <h3>Starter</h3>
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">$0<span style="font-size: 1rem; color: var(--gray);">/mo</span></h1>
                <ul style="list-style: none; color: var(--gray); margin-bottom: 2rem;">
                    <li>Standard Support</li>
                    <li>Limited Global Calls</li>
                    <li>Basic Security</li>
                </ul>
                <a href="{{ route('register') }}" class="btn-login btn-outline" style="display: block;">Get Started</a>
            </div>
            <div class="feature-card" style="text-align: center; border: 2px solid var(--primary); position: relative;">
                <span style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--primary); color: white; padding: 2px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">POPULAR</span>
                <h3>Pro</h3>
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">$29<span style="font-size: 1rem; color: var(--gray);">/mo</span></h1>
                <ul style="list-style: none; color: var(--gray); margin-bottom: 2rem;">
                    <li>Priority Support</li>
                    <li>Unlimited Global Calls</li>
                    <li>Advanced Encryption</li>
                </ul>
                <a href="{{ route('register') }}" class="btn-login btn-primary" style="display: block;">Try Pro</a>
            </div>
            <div class="feature-card" style="text-align: center;">
                <h3>Enterprise</h3>
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">Custom</h1>
                <ul style="list-style: none; color: var(--gray); margin-bottom: 2rem;">
                    <li>Dedicated Manager</li>
                    <li>Custom Infrastructure</li>
                    <li>24/7 Premium Support</li>
                </ul>
                <a href="#" class="btn-login btn-outline" style="display: block;">Contact Sales</a>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <h2 class="logo" style="color: white">Altfon<span>booth</span></h2>
                <p>Connecting the world through innovative telecommunications solutions.</p>
            </div>
            <div class="footer-col">
                <h4>Product</h4>
                <ul>
                    <li><a href="#">Features</a></li>
                    <li><a href="#">Security</a></li>
                    <li><a href="#">Business</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Status</a></li>
                    <li><a href="#">API Docs</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 Altfon Booth. All rights reserved.</p>
            <div style="display: flex; gap: 1rem;">
                <a href="#" style="color: #666; text-decoration: none;">Privacy Policy</a>
                <a href="#" style="color: #666; text-decoration: none;">Terms of Service</a>
            </div>
        </div>
    </footer>
</body>
</html>
