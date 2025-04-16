<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriMarket Footer</title>
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f3f4f6; /* Ensure body background doesn't interfere */
        }

        .footer {
            background-color: #ffffff; /* Match the navbar background */
            padding: 2rem 0; /* Add padding for spacing */
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2.5rem;
            align-items: start;
        }

        .footer-section {
            padding: 1.5rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8); /* Slightly transparent white for a modern look */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .footer-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .footer-section h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #047857; /* Vibrant green */
            margin-bottom: 1.25rem;
            position: relative;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: -0.3rem;
            left: 0;
            width: 2.5rem;
            height: 3px;
            background: linear-gradient(to right, #047857, #34d399);
            border-radius: 2px;
        }

        .footer-text {
            color: #4b5563; /* Dark gray for text */
            font-size: 1rem;
            line-height: 1.7;
            opacity: 0.9;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.85rem;
        }

        .footer-links a {
            color: #3b82f6; /* Primary color for links */
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease, padding-left 0.3s ease;
        }

        .footer-links a:hover {
            color: #1d4ed8; /* Darker shade on hover */
            padding-left: 8px;
        }

        .social-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            margin-top: 1.5rem;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .social-link:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .social-link svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        /* Specific colors for each social icon */
        .social-link.facebook {
            color: #1877f2; /* Facebook blue */
        }

        .social-link.twitter {
            color: #000000; /* Twitter black */
        }

        .social-link.instagram svg {
            background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%); /* Instagram gradient */
            border-radius: 5px; /* Slight rounding for gradient */
            padding: 2px; /* Ensure gradient doesn't clip */
        }

        .social-link.instagram path {
            fill: white; /* White fill for Instagram icon to contrast with gradient */
        }

        .footer-bottom {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(209, 213, 219, 0.5);
            text-align: center;
        }

        .footer-bottom .footer-text {
            font-size: 0.95rem;
            color: #6b7280; /* Medium gray for footer text */
            font-weight: 400;
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-section h3::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .social-links {
                justify-content: center;
            }

            .footer-links a:hover {
                padding-left: 0; /* Disable padding shift on mobile */
            }

            .footer-section:hover {
                transform: none; /* Disable lift effect on mobile */
            }
        }
    </style>
</head>
<body>
    <footer class="footer">
        <div class="container footer-content">
            <div class="footer-section">
                <h3>AgriMarket</h3>
                <p class="footer-text">Connecting farmers and consumers directly.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="privacy.php">Privacy Policy</a></li>
                    <li><a href="terms.php">Terms of Service</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Connect With Us</h3>
                <div class="social-links">
                    <a href="#" class="social-link facebook">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879v-6.987h-2.54v-2.892h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.892h-2.33v6.987C18.343 21.128 22 16.991 22 12z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-link twitter">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/>
                        </svg>
                    </a>
                    <a href="#" class="social-link instagram">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.332.014 7.052.072c-4.95.232-6.532 2.318-6.764 6.764C.014 8.332 0 8.741 0 12c0 3.259.014 3.668.072 4.948.232 4.946 2.318 6.532 6.764 6.764 1.28.058 1.689.072 4.948.072s3.668-.014 4.948-.072c4.946-.232 6.532-2.318 6.764-6.764.058-1.28.072-1.689.072-4.948s-.014-3.668-.072-4.948c-.232-4.946-2.318-6.532-6.764-6.764C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zm0 10.162a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100-2.88 1.44 1.44 0 000 2.88z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p class="footer-text">© <?php echo date('Y'); ?> AgriMarket. All rights reserved. This business is fictitious and part of a university course.</p>
            </div>
        </div>
    </footer>
</body>
</html>