document.addEventListener('DOMContentLoaded', () => {
    // Initialize GSAP and ScrollTrigger
    gsap.registerPlugin(ScrollTrigger);
    
    // Variables
    const slidesContainer = document.querySelector('.slides-container');
    const slides = document.querySelectorAll('.slide');
    const indicators = document.querySelectorAll('.indicator');
    const airplane = document.querySelector('.airplane');
    const checkpoints = document.querySelectorAll('.checkpoint');
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    const navbar = document.querySelector('.navbar');
    const progressBar = document.querySelector('.progress-bar');
    
    let currentSlide = -1;
    const totalSlides = slides.length;
    const slideWidth = 100 / totalSlides;
    
    // Preloader animation
    function animatePreloader() {
        const preloader = document.querySelector('.preloader');
        const planeLine = document.querySelector('.plane-line');
        const plane = document.querySelector('.plane');
        const loadingText = document.querySelector('.loading-text');
        
        // Animate plane line
        gsap.to(planeLine, {
            width: '100%',
            duration: 1.5,
            ease: "power2.inOut"
        });
        
        // Animate plane
        gsap.to(plane, {
            left: '100%',
            duration: 1.5,
            ease: "power2.inOut"
        });
        
        // Animate loading text
        gsap.to(loadingText, {
            scale: 1.1,
            opacity: 0,
            duration: 0.5,
            delay: 1.5,
            ease: "power2.in"
        });
        
        // Hide preloader
        gsap.to(preloader, {
            opacity: 0,
            duration: 0.5,
            delay: 2,
            ease: "power2.in",
            onComplete: () => {
                preloader.style.display = 'none';
                // Animate initial slide content
                animateSlideContent(0);
            }
        });
    }
    
    // Disable default scrolling behavior
    document.body.style.overflow = 'hidden';
    
    // Mobile menu toggle
    mobileMenuToggle.addEventListener('click', () => {
        mobileMenuToggle.classList.toggle('active');
        mobileMenu.classList.toggle('active');
        
        // Animate hamburger icon to X
        const bars = mobileMenuToggle.querySelectorAll('.bar');
        if (mobileMenuToggle.classList.contains('active')) {
            gsap.to(bars[0], { rotate: 45, translateY: 8, duration: 0.3 });
            gsap.to(bars[1], { opacity: 0, duration: 0.3 });
            gsap.to(bars[2], { rotate: -45, translateY: -8, duration: 0.3 });
        } else {
            gsap.to(bars[0], { rotate: 0, translateY: 0, duration: 0.3 });
            gsap.to(bars[1], { opacity: 1, duration: 0.3 });
            gsap.to(bars[2], { rotate: 0, translateY: 0, duration: 0.3 });
        }
    });
    
    // Background shapes animation
    function animateBackgroundShapes() {
        const shapes = document.querySelectorAll('.bg-shape');
        
        shapes.forEach((shape, index) => {
            gsap.to(shape, {
                x: `${(Math.random() - 0.5) * 50}px`,
                y: `${(Math.random() - 0.5) * 50}px`,
                duration: 10 + index * 2,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut"
            });
        });
    }
    
    // Cloud animations
    function animateClouds() {
        const clouds = document.querySelectorAll('.cloud');
        
        clouds.forEach((cloud, index) => {
            gsap.to(cloud, {
                x: `${(Math.random() - 0.5) * 100}px`,
                duration: 20 + index * 5,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut"
            });
        });
    }
    
    // Progress bar update on scroll
    function updateProgressBar() {
        const scrollProgress = (currentSlide / (totalSlides - 1)) * 100;
        progressBar.style.width = `${scrollProgress}%`;
    }
    
    // Function to navigate to a specific slide
    function goToSlide(index) {
        // Show the airplane and flight path during transition
        const airplaneContainer = document.querySelector('.airplane-container');
        airplaneContainer.classList.add('visible');
        
        // Bound the index within valid range
        index = Math.max(0, Math.min(index, totalSlides - 1));
        
        // Update current slide
        currentSlide = index;
        
        // Move slides container
        gsap.to(slidesContainer, {
            x: `-${currentSlide * slideWidth}%`,
            duration: 0.8,
            ease: "power2.inOut"
        });
        
        // Update indicators
        indicators.forEach((indicator, i) => {
            if (i === currentSlide) {
                indicator.classList.add('active');
            } else {
                indicator.classList.remove('active');
            }
        });
        
        // Update checkpoints
        checkpoints.forEach((checkpoint, i) => {
            if (i === currentSlide) {
                checkpoint.classList.add('active');
            } else {
                checkpoint.classList.remove('active');
            }
        });
        
        // Move the airplane
        const airplanePosition = checkpoints[currentSlide].getBoundingClientRect().left;
        gsap.to(airplane, {
            left: `${airplanePosition}px`,
            duration: 0.8,
            ease: "power2.inOut",
            onComplete: () => {
                airplane.classList.add('bounce');
                setTimeout(() => {
                    airplane.classList.remove('bounce');
                }, 500);
                
                // Hide the airplane and flight path after transition completes
                setTimeout(() => {
                    airplaneContainer.classList.remove('visible');
                }, 500);
            }
        });
        
        // Update progress bar
        updateProgressBar();
        
        // Animate content of the current slide
        animateSlideContent(currentSlide);
    }
    
    // Counter animation function
    function animateCounters() {
        const counters = document.querySelectorAll('.counter');
        
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            const duration = 2000; // 2 seconds
            const step = target / duration * 10; // Update every 10ms
            
            let current = 0;
            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                counter.textContent = Math.round(current);
            }, 10);
        });
    }
    
    // Function to animate content within a slide
    function animateSlideContent(slideIndex) {
        // Reset animations for all slides
        slides.forEach((slide, index) => {
            if (index !== slideIndex) {
                // Reset title and text animations
                const title = slide.querySelector('.slide-title');
                const textReveals = slide.querySelectorAll('.text-reveal');
                const teamMembers = slide.querySelectorAll('.team-member');
                const timelineItems = slide.querySelectorAll('.timeline-item');
                const missionBox = slide.querySelector('.mission-box');
                const visionBox = slide.querySelector('.vision-box');
                const destinations = slide.querySelectorAll('.destination');
                const featureItems = slide.querySelectorAll('.feature-item');
                const ctaContainer = slide.querySelector('.cta-container');
                const imageContainer = slide.querySelector('.reveal-image');
                
                if (title) title.classList.remove('active');
                textReveals.forEach(item => item.classList.remove('active'));
                teamMembers.forEach(item => item.classList.remove('active'));
                timelineItems.forEach(item => item.classList.remove('active'));
                if (missionBox) missionBox.classList.remove('active');
                if (visionBox) visionBox.classList.remove('active');
                destinations.forEach(item => item.classList.remove('active'));
                featureItems.forEach(item => item.classList.remove('active'));
                if (ctaContainer) ctaContainer.classList.remove('active');
                if (imageContainer) imageContainer.classList.remove('active');
            }
        });
        
        // Get current slide elements
        const currentSlide = slides[slideIndex];
        const title = currentSlide.querySelector('.slide-title');
        const textReveals = currentSlide.querySelectorAll('.text-reveal');
        const teamMembers = currentSlide.querySelectorAll('.team-member');
        const timelineItems = currentSlide.querySelectorAll('.timeline-item');
        const missionBox = currentSlide.querySelector('.mission-box');
        const visionBox = currentSlide.querySelector('.vision-box');
        const destinations = currentSlide.querySelectorAll('.destination');
        const featureItems = currentSlide.querySelectorAll('.feature-item');
        const ctaContainer = currentSlide.querySelector('.cta-container');
        const imageContainer = currentSlide.querySelector('.reveal-image');
        
        // Set transition delays for each timeline item
        timelineItems.forEach((item, index) => {
            item.style.setProperty('--item-index', index);
        });
        
        // Adjust animation timing for mobile devices
        const isMobile = isMobileDevice();
        const baseDelay = isMobile ? 100 : 200;
        const baseTimeout = isMobile ? 300 : 400;
        
        // Animate image container
        if (imageContainer) {
            setTimeout(() => {
                imageContainer.classList.add('active');
            }, baseTimeout / 2);
        }
        
        // Animate slide title
        if (title) {
            setTimeout(() => {
                title.classList.add('active');
            }, baseTimeout);
        }
        
        // Animate text reveals
        textReveals.forEach((text, index) => {
            setTimeout(() => {
                text.classList.add('active');
            }, baseTimeout + baseDelay + index * baseDelay);
        });
        
        // Animate team members
        teamMembers.forEach((member, index) => {
            setTimeout(() => {
                member.classList.add('active');
            }, baseTimeout * 2 + index * baseDelay);
        });
        
        // Animate timeline items
        timelineItems.forEach((item, index) => {
            setTimeout(() => {
                item.classList.add('active');
            }, baseTimeout * 2 + index * baseDelay);
        });
        
        // Animate mission and vision boxes
        if (missionBox) {
            setTimeout(() => {
                missionBox.classList.add('active');
            }, baseTimeout * 2);
        }
        
        if (visionBox) {
            setTimeout(() => {
                visionBox.classList.add('active');
            }, baseTimeout * 2 + baseDelay);
        }
        
        // Animate destinations
        destinations.forEach((dest, index) => {
            setTimeout(() => {
                dest.classList.add('active');
            }, baseTimeout * 3 + index * (baseDelay / 2));
        });
        
        // Animate feature items
        featureItems.forEach((feature, index) => {
            setTimeout(() => {
                feature.classList.add('active');
            }, baseTimeout * 2 + index * baseDelay);
        });
        
        // Animate CTA
        if (ctaContainer) {
            setTimeout(() => {
                ctaContainer.classList.add('active');
            }, baseTimeout * 3.5);
        }
        
        // Animate counters if they exist in this slide
        if (currentSlide.querySelectorAll('.counter').length > 0) {
            setTimeout(animateCounters, baseTimeout * 3);
        }
    }
    
    // Handle mouse wheel events for slide navigation
    let isScrolling = false;
    
    window.addEventListener('wheel', (event) => {
        if (isScrolling) return;
        
        isScrolling = true;
        setTimeout(() => { isScrolling = false; }, 1000); // Debounce
        
        // Show the airplane container when scrolling begins
        const airplaneContainer = document.querySelector('.airplane-container');
        airplaneContainer.classList.add('visible');
        
        if (event.deltaY > 0) {
            // Scroll down - go to next slide
            goToSlide(currentSlide + 1);
        } else {
            // Scroll up - go to previous slide
            goToSlide(currentSlide - 1);
        }
    });
    
    // Handle touch events for mobile
    let touchStartY = 0;
    let touchEndY = 0;
    
    document.addEventListener('touchstart', (event) => {
        touchStartY = event.touches[0].clientY;
    }, false);
    
    document.addEventListener('touchend', (event) => {
        touchEndY = event.changedTouches[0].clientY;
        handleSwipe();
    }, false);
    
    function handleSwipe() {
        if (isScrolling) return;
        
        const swipeDistance = touchStartY - touchEndY;
        const minSwipeDistance = 50;
        
        if (Math.abs(swipeDistance) < minSwipeDistance) return;
        
        isScrolling = true;
        setTimeout(() => { isScrolling = false; }, 1000);
        
        // Show the airplane container when swiping begins
        const airplaneContainer = document.querySelector('.airplane-container');
        airplaneContainer.classList.add('visible');
        
        if (swipeDistance > 0) {
            // Swipe up - go to next slide
            goToSlide(currentSlide + 1);
        } else {
            // Swipe down - go to previous slide
            goToSlide(currentSlide - 1);
        }
    }
    
    // Handle indicator clicks
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            // Show the airplane container when indicator is clicked
            const airplaneContainer = document.querySelector('.airplane-container');
            airplaneContainer.classList.add('visible');
            goToSlide(index);
        });
    });
    
    // Handle checkpoint clicks
    checkpoints.forEach((checkpoint, index) => {
        checkpoint.addEventListener('click', () => {
            goToSlide(index);
        });
    });
    
    // Handle keyboard navigation
    document.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
            // Show the airplane container when arrow keys are pressed
            const airplaneContainer = document.querySelector('.airplane-container');
            airplaneContainer.classList.add('visible');
            goToSlide(currentSlide + 1);
        } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
            // Show the airplane container when arrow keys are pressed
            const airplaneContainer = document.querySelector('.airplane-container');
            airplaneContainer.classList.add('visible');
            goToSlide(currentSlide - 1);
        }
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Function to check if device is mobile
    function isMobileDevice() {
        return (window.innerWidth <= 768) || ('ontouchstart' in window);
    }
    
    // Function to handle orientation change
    function handleOrientationChange() {
        // Adjust the position of elements after orientation change
        const currentSlideRect = slides[currentSlide].getBoundingClientRect();
        const airplaneContainer = document.querySelector('.airplane-container');
        
        // Adjust airplane position for different screen sizes
        if (window.innerWidth <= 768) {
            airplaneContainer.style.top = '30%';
        } else if (window.innerWidth <= 576) {
            airplaneContainer.style.top = '20%';
        } else if (window.innerWidth <= 400) {
            airplaneContainer.style.top = '15%';
        } else {
            airplaneContainer.style.top = '50%';
        }
        
        // Reset slide container position
        gsap.to(slidesContainer, {
            x: `-${currentSlide * slideWidth}%`,
            duration: 0.3
        });
        
        // Reset airplane position if visible
        if (checkpoints[currentSlide]) {
            const airplanePosition = checkpoints[currentSlide].getBoundingClientRect().left;
            gsap.to(airplane, {
                left: `${airplanePosition}px`,
                duration: 0.3
            });
        }
        
        // If it's a small screen in landscape, adjust content scroll
        if (window.innerWidth < 992 && window.innerHeight < 600) {
            document.querySelectorAll('.slide-content').forEach(content => {
                content.style.maxHeight = '80vh';
                content.style.overflowY = 'auto';
            });
        }
    }
    
    // Listen for orientation changes
    window.addEventListener('orientationchange', () => {
        setTimeout(handleOrientationChange, 200);
    });
    
    // Listen for resize events
    window.addEventListener('resize', () => {
        // Debounce the resize handler
        clearTimeout(window.resizeTimer);
        window.resizeTimer = setTimeout(handleOrientationChange, 250);
    });
    
    // Initialize animations
    animatePreloader();
    animateBackgroundShapes();
    animateClouds();
});
// Make indicators clickable
document.addEventListener('DOMContentLoaded', function() {
    const indicators = document.querySelectorAll('.indicator');
    
    indicators.forEach(indicator => {
      indicator.addEventListener('click', function() {
        const slideIndex = parseInt(this.getAttribute('data-slide')) - 1;
        if (window.goToSlide) {
          window.goToSlide(slideIndex);
        }
      });
    });
  });