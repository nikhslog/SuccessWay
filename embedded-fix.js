// Detect if we're in an iframe
const isEmbedded = window.self !== window.top;

if (isEmbedded) {
    function enhancedDebug() {
        // Create debug panel if not exists
        if (!document.getElementById('debug-panel')) {
          const debugPanel = document.createElement('div');
          debugPanel.id = 'debug-panel';
          debugPanel.style.position = 'fixed';
          debugPanel.style.bottom = '10px';
          debugPanel.style.right = '10px';
          debugPanel.style.width = '300px';
          debugPanel.style.height = '200px';
          debugPanel.style.background = 'rgba(0,0,0,0.8)';
          debugPanel.style.color = 'white';
          debugPanel.style.padding = '10px';
          debugPanel.style.fontSize = '12px';
          debugPanel.style.fontFamily = 'monospace';
          debugPanel.style.overflow = 'auto';
          debugPanel.style.zIndex = '9999999';
          document.body.appendChild(debugPanel);
        }
  
        const debugPanel = document.getElementById('debug-panel');
        
        // Clear panel
        debugPanel.innerHTML = '<h3>Element Structure Debug</h3>';
        
        // Check all important elements
        const elements = [
          '.scroll-container',
          '.slides-container',
          '.slide',
          '.airplane-container',
          '.flight-path',
          '.airplane',
          '.checkpoint'
        ];
        
        elements.forEach(selector => {
          const matches = document.querySelectorAll(selector);
          debugPanel.innerHTML += `<p>${selector}: ${matches.length} found</p>`;
          
          if (matches.length > 0) {
            const firstMatch = matches[0];
            debugPanel.innerHTML += `<p>- z-index: ${getComputedStyle(firstMatch).zIndex}</p>`;
            debugPanel.innerHTML += `<p>- visibility: ${getComputedStyle(firstMatch).visibility}</p>`;
            debugPanel.innerHTML += `<p>- opacity: ${getComputedStyle(firstMatch).opacity}</p>`;
            debugPanel.innerHTML += `<p>- position: ${getComputedStyle(firstMatch).position}</p>`;
          }
        });
        
        // Check stacking context
        debugPanel.innerHTML += '<h3>Stacking Context</h3>';
        let element = document.querySelector('.airplane-container');
        let path = [];
        
        if (element) {
          let current = element;
          while (current && current !== document.body) {
            path.push({
              element: current.tagName + (current.id ? '#' + current.id : ''),
              zIndex: getComputedStyle(current).zIndex,
              position: getComputedStyle(current).position
            });
            current = current.parentElement;
          }
          
          path.forEach(p => {
            debugPanel.innerHTML += `<p>${p.element}: z-index: ${p.zIndex}, position: ${p.position}</p>`;
          });
        }
      }
  
      function testOverlay() {
        // Create test overlay
        const testOverlay = document.createElement('div');
        testOverlay.style.position = 'fixed';
        testOverlay.style.top = '0';
        testOverlay.style.left = '0';
        testOverlay.style.width = '100%';
        testOverlay.style.height = '100%';
        testOverlay.style.backgroundColor = 'rgba(255, 0, 0, 0.5)';  // Semi-transparent red
        testOverlay.style.zIndex = '999999';  // Very high z-index
        testOverlay.style.display = 'flex';
        testOverlay.style.alignItems = 'center';
        testOverlay.style.justifyContent = 'center';
        
        const message = document.createElement('div');
        message.textContent = 'TEST OVERLAY - CLICK TO DISMISS';
        message.style.backgroundColor = 'white';
        message.style.padding = '20px';
        message.style.borderRadius = '10px';
        message.style.fontSize = '24px';
        
        testOverlay.appendChild(message);
        document.body.appendChild(testOverlay);
        
        // Remove overlay on click
        testOverlay.addEventListener('click', () => {
          document.body.removeChild(testOverlay);
        });
      }
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Embedded mode detected - applying fixes");
        
        // Create debug panel
        const debugPanel = document.createElement('div');
        debugPanel.style.position = 'fixed';
        debugPanel.style.bottom = '10px';
        debugPanel.style.left = '10px';
        debugPanel.style.zIndex = '9999';
        debugPanel.style.background = 'rgba(0,0,0,0.7)';
        debugPanel.style.color = 'white';
        debugPanel.style.padding = '10px';
        debugPanel.style.borderRadius = '5px';
        debugPanel.style.fontSize = '12px';
        debugPanel.style.maxWidth = '300px';
        debugPanel.style.maxHeight = '200px';
        debugPanel.style.overflow = 'auto';
        debugPanel.style.fontFamily = 'monospace';
        debugPanel.innerHTML = 'Debug Panel<br>';
        document.body.appendChild(debugPanel);
        
        // Log function
        window.debugLog = function(message) {
            console.log(message);
            debugPanel.innerHTML += message + '<br>';
            debugPanel.scrollTop = debugPanel.scrollHeight;
        };
        
        // Hide navigation elements
        const elementsToHide = [
            '.navbar',
            '.mobile-menu',
            '.mobile-menu-toggle',
            '.preloader',
            '.progress-container'
        ];
        
        elementsToHide.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(el => {
                if (el) el.style.display = 'none';
            });
        });
        
        debugLog("Creating transition overlay");
        
        // Create a completely separate transition layer
        const transitionLayer = document.createElement('div');
        transitionLayer.className = 'transition-layer';
        transitionLayer.innerHTML = `
            <div class="transition-background"></div>
            <div class="transition-flight-path"></div>
            <div class="transition-airplane">
                <i class="fas fa-plane"></i>
            </div>
            <div class="transition-checkpoints">
                <div class="transition-checkpoint" data-index="0"></div>
                <div class="transition-checkpoint" data-index="1"></div>
                <div class="transition-checkpoint" data-index="2"></div>
                <div class="transition-checkpoint" data-index="3"></div>
            </div>
        `;
        document.body.appendChild(transitionLayer);
        
        // Position checkpoints
        function positionCheckpoints() {
            const checkpoints = document.querySelectorAll('.transition-checkpoint');
            const containerWidth = window.innerWidth * 0.8;
            const containerLeft = window.innerWidth * 0.1;
            
            checkpoints.forEach((checkpoint, index) => {
                const position = containerLeft + (containerWidth * index / (checkpoints.length - 1));
                checkpoint.style.left = `${position}px`;
            });
            
            // Position the plane at first checkpoint
            const airplane = document.querySelector('.transition-airplane');
            if (airplane && checkpoints[0]) {
                airplane.style.left = `${checkpoints[0].style.left}`;
            }
            
            // Size the flight path
            const flightPath = document.querySelector('.transition-flight-path');
            if (flightPath) {
                flightPath.style.left = `${containerLeft}px`;
                flightPath.style.width = `${containerWidth}px`;
            }
        }
        
        // Track current slide
        let currentSlide = 0;
        const totalSlides = document.querySelectorAll('.slide').length;
        
        // Scroll control variables
        let isProcessingScroll = false;
        let scrollLockTimeout = null;
        
        // Force all slides to show their content
        function forceShowAllContent() {
            debugLog("Forcing content visibility");
            
            const slides = document.querySelectorAll('.slide');
            slides.forEach((slide, index) => {
                // Make the slide visible
                slide.style.opacity = '1';
                slide.style.visibility = 'visible';
                
                // Force visibility on slide content
                const slideContent = slide.querySelector('.slide-content');
                if (slideContent) {
                    slideContent.style.opacity = '1';
                    slideContent.style.visibility = 'visible';
                    
                    // Force visibility on all internal elements
                    const allElements = slideContent.querySelectorAll('*');
                    allElements.forEach(el => {
                        el.style.opacity = '1';
                        el.style.visibility = 'visible';
                        
                        // Remove any transform that might hide content
                        if (el.classList.contains('text-reveal') || 
                            el.classList.contains('slide-title') || 
                            el.classList.contains('reveal-image')) {
                            el.style.transform = 'none';
                        }
                    });
                }
                
                // Force animation for this slide
                if (typeof window.animateSlideContent === 'function') {
                    try {
                        window.animateSlideContent(index);
                    } catch (e) {
                        console.error("Error animating slide:", e);
                    }
                }
            });
        }
        
        // Show transition layer
        function showTransitionLayer() {
            debugLog("Showing transition layer");
            transitionLayer.classList.add('active');
            
            // Hide the slides
            document.body.classList.add('transitioning');
        }
        
        // Hide transition layer
        function hideTransitionLayer() {
            debugLog("Hiding transition layer");
            transitionLayer.classList.remove('active');
            
            // Show the slides again
            document.body.classList.remove('transitioning');
        }
        
        // Animate plane in transition layer
        function animatePlaneTransition(targetIndex) {
            enhancedDebug();
            testOverlay();
            debugLog(`Animating plane to checkpoint ${targetIndex}`);
            
            showTransitionLayer();
            
            // Get target checkpoint position
            const targetCheckpoint = document.querySelector(`.transition-checkpoint[data-index="${targetIndex}"]`);
            if (!targetCheckpoint) {
                debugLog("Target checkpoint not found");
                hideTransitionLayer();
                return;
            }
            
            // Update active checkpoint
            document.querySelectorAll('.transition-checkpoint').forEach((checkpoint, index) => {
                if (index === targetIndex) {
                    checkpoint.classList.add('active');
                } else {
                    checkpoint.classList.remove('active');
                }
            });
            
            // Animate plane
            const airplane = document.querySelector('.transition-airplane');
            if (airplane) {
                airplane.style.transition = 'left 0.8s cubic-bezier(0.25, 1, 0.5, 1)';
                airplane.style.left = targetCheckpoint.style.left;
                
                // Add bounce effect after transition
                setTimeout(() => {
                    airplane.classList.add('bounce');
                    
                    // Remove bounce class after animation
                    setTimeout(() => {
                        airplane.classList.remove('bounce');
                        
                        // Hide transition layer after animation completes
                        hideTransitionLayer();
                    }, 500);
                }, 800);
            } else {
                debugLog("Airplane element not found");
                hideTransitionLayer();
            }
        }
        
        // Improved goToSlide function
        window.goToSlide = function(index) {
            debugLog(`Going to slide: ${index}`);
            
            const slidesContainer = document.querySelector('.slides-container');
            const indicators = document.querySelectorAll('.indicator');
            const slides = document.querySelectorAll('.slide');
            
            if (!slidesContainer || !slides.length) {
                debugLog("Slides container or slides not found");
                return;
            }
            
            // Ensure index is valid
            const targetSlide = Math.max(0, Math.min(index, slides.length - 1));
            
            // Update current slide tracking
            currentSlide = targetSlide;
            
            // Move slides container
            const slideWidth = 100 / slides.length;
            slidesContainer.style.transform = `translateX(-${currentSlide * slideWidth}%)`;
            
            // Update indicators
            if (indicators && indicators.length) {
                indicators.forEach((indicator, i) => {
                    if (i === currentSlide) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
            }
            
            // Force all content to be visible
            forceShowAllContent();
            
            // Directly force animation for the current slide
            try {
                if (typeof window.animateSlideContent === 'function') {
                    window.animateSlideContent(currentSlide);
                }
            } catch (e) {
                console.error("Error animating current slide:", e);
            }
            
            // Notify parent about slide change
            window.parent.postMessage(`slideChanged:${currentSlide}`, '*');
        };
        
        // Improved wheel event handler with strict single-scroll control
        document.addEventListener('wheel', function(e) {
            e.preventDefault();
            
            // If already processing a scroll event, ignore this one
            if (isProcessingScroll) {
                return;
            }
            
            // Get scroll direction
            const scrollDown = e.deltaY > 0;
            
            // Calculate target slide
            const targetSlide = scrollDown ? 
                Math.min(currentSlide + 1, totalSlides - 1) : 
                Math.max(currentSlide - 1, 0);
            
            debugLog(`Wheel event: direction=${scrollDown ? 'down' : 'up'}, target=${targetSlide}`);
            
            // Check if we're at the limits
            if ((currentSlide === 0 && !scrollDown) || (currentSlide === totalSlides - 1 && scrollDown)) {
                // Pass the scroll event to parent for section navigation
                window.parent.postMessage(scrollDown ? 'scrollDown' : 'scrollUp', '*');
            } else {
                // Navigate between slides within the about section
                
                // Start airplane animation
                animatePlaneTransition(targetSlide);
                
                // After a delay to complete airplane animation, change the slide
                setTimeout(() => {
                    goToSlide(targetSlide);
                }, 1500);
            }
            
            // Lock scrolling temporarily
            isProcessingScroll = true;
            
            // Set a timeout to allow the next scroll after animation completes
            clearTimeout(scrollLockTimeout);
            scrollLockTimeout = setTimeout(function() {
                isProcessingScroll = false;
            }, 2000); // Longer lock to account for animation
            
        }, { passive: false });
        
        // Also handle touch events for mobile with same approach
        let touchStartY = 0;
        document.addEventListener('touchstart', function(e) {
            touchStartY = e.touches[0].clientY;
        }, { passive: false });
        
        document.addEventListener('touchend', function(e) {
            if (isProcessingScroll) return;
            
            const touchEndY = e.changedTouches[0].clientY;
            const scrollDown = touchStartY > touchEndY;
            
            if (Math.abs(touchStartY - touchEndY) < 30) {
                // Swipe was too short, ignore it
                return;
            }
            
            // Calculate target slide
            const targetSlide = scrollDown ? 
                Math.min(currentSlide + 1, totalSlides - 1) : 
                Math.max(currentSlide - 1, 0);
            
            debugLog(`Touch event: direction=${scrollDown ? 'down' : 'up'}, target=${targetSlide}`);
            
            // Check if we're at the limits
            if ((currentSlide === 0 && !scrollDown) || (currentSlide === totalSlides - 1 && scrollDown)) {
                // Pass the scroll event to parent for section navigation
                window.parent.postMessage(scrollDown ? 'scrollDown' : 'scrollUp', '*');
            } else {
                // Navigate between slides within the about section
                
                // Start airplane animation
                animatePlaneTransition(targetSlide);
                
                // After a delay to complete airplane animation, change the slide
                setTimeout(() => {
                    goToSlide(targetSlide);
                }, 1500);
            }
            
            // Lock scrolling temporarily
            isProcessingScroll = true;
            
            // Set a timeout to allow the next scroll after animation completes
            clearTimeout(scrollLockTimeout);
            scrollLockTimeout = setTimeout(function() {
                isProcessingScroll = false;
            }, 2000);
            
            e.preventDefault();
        }, { passive: false });
        
        // Listen for messages from parent
        window.addEventListener('message', function(event) {
            if (event.data === 'initialize') {
                debugLog("Initializing from parent message");
                setTimeout(function() {
                    // Position checkpoints
                    positionCheckpoints();
                    
                    // Force all content to be visible
                    forceShowAllContent();
                    
                    // Go to first slide
                    goToSlide(0);
                    
                    // Hide transition layer initially
                    hideTransitionLayer();
                }, 500);
            } else if (typeof event.data === 'string' && event.data.startsWith('goToSlide:')) {
                const slideIndex = parseInt(event.data.split(':')[1]);
                debugLog(`Received goToSlide:${slideIndex} from parent`);
                
                // Start airplane animation
                animatePlaneTransition(slideIndex);
                
                // After a delay to complete airplane animation, change the slide
                setTimeout(() => {
                    goToSlide(slideIndex);
                }, 1500);
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', positionCheckpoints);
        
        // Add CSS for animations and transition layer
        const style = document.createElement('style');
        style.textContent = `
            @keyframes bounce {
                0%, 100% { transform: translate(-50%, -50%); }
                50% { transform: translate(-50%, -70%); }
            }
            
            /* Main container styling */
            body.transitioning .slides-container,
            body.transitioning .slide-indicators,
            body.transitioning .scroll-container > *:not(.transition-layer) {
                opacity: 0 !important;
                visibility: hidden !important;
                transition: opacity 0.3s ease, visibility 0.3s ease;
            }
            
            /* Transition layer */
            .transition-layer {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9998;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease, visibility 0.3s ease;
                pointer-events: none;
            }
            
            .transition-layer.active {
                opacity: 1;
                visibility: visible;
            }
            
            .transition-background {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: #e7fbf7; /* Match your site's background color */
            }
            
            .transition-flight-path {
                position: absolute;
                top: 50%;
                height: 2px;
                background-color: rgba(84, 183, 165, 0.2);
                transform: translateY(-50%);
            }
            
            .transition-airplane {
                position: absolute;
                top: 50%;
                width: 40px;
                height: 40px;
                background-color: white;
                border-radius: 50%;
                transform: translate(-50%, -50%);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                color: #54b7a5;
                font-size: 20px;
            }
            
            .transition-airplane.bounce {
                animation: bounce 0.5s ease;
            }
            
            .transition-checkpoints {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }
            
            .transition-checkpoint {
                position: absolute;
                top: 50%;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background-color: rgba(84, 183, 165, 0.3);
                transform: translate(-50%, -50%);
                transition: background-color 0.3s ease;
            }
            
            .transition-checkpoint.active {
                background-color: #54b7a5;
                box-shadow: 0 0 0 4px rgba(84, 183, 165, 0.2);
            }
        `;
        document.head.appendChild(style);
        
        // Load Font Awesome for plane icon if not already loaded
        if (!document.querySelector('link[href*="font-awesome"]')) {
            const faLink = document.createElement('link');
            faLink.rel = 'stylesheet';
            faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
            document.head.appendChild(faLink);
        }
        
        // Log initial state
        debugLog("Embedded mode activated");
        debugLog(`Total slides: ${totalSlides}`);
        debugLog(`Scroll container: ${document.querySelector('.scroll-container') ? 'Found' : 'Missing'}`);
        debugLog(`Slides container: ${document.querySelector('.slides-container') ? 'Found' : 'Missing'}`);
    });
}