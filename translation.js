
    // Simple script to detect when Google Translate is active
    function checkForTranslation() {
        // Check if we're translated to French
        if (document.cookie.indexOf('googtrans=/en/fr') > -1) {
            document.documentElement.classList.add('translated-fr');
        } else {
            document.documentElement.classList.remove('translated-fr');
        }
    }
    
    // Run when page loads
    document.addEventListener('DOMContentLoaded', checkForTranslation);
    
    // Also check periodically in case translation happens after page load
    setInterval(checkForTranslation, 1000);

    // Wait for page to fully load
    document.addEventListener('DOMContentLoaded', function() {
      // Create a function to replace text in all elements
      function replaceRegistrationText() {
        // Check if page is translated to French
        const isFrench = 
          document.documentElement.lang === 'fr' || 
          document.cookie.indexOf('googtrans=/en/fr') > -1 ||
          document.querySelector('.goog-te-combo')?.value === 'fr';
        
        // If not French, no need to do anything
        if (!isFrench) return;
        
        // Find all text nodes in the document
        const walker = document.createTreeWalker(
          document.body,
          NodeFilter.SHOW_TEXT,
          null,
          false
        );
        
        // Array of translations
        const translations = {
          'Register': 'Inscrivez-vous',
          'register': 'Inscrivez-vous',
          'REGISTER': 'INSCRIVEZ-VOUS',
          'SuccessWay': 'Le chemin du succès',
          'Successway': 'Le chemin du succès',
          'successway': 'le chemin du succès',
          'SUCCESSWAY': 'LE CHEMIN DU SUCCÈS',
          'Home': 'Accueil',
          'About': 'À propos de nous',
          'Services': 'Nos services',
          'Pricing': 'Tarifs',
          'Contact': 'Contactez-nous'
        };
        
        // Loop through all text nodes
        let node;
        while (node = walker.nextNode()) {
          // Skip script and style tags
          if (node.parentNode.tagName === 'SCRIPT' || 
              node.parentNode.tagName === 'STYLE') {
            continue;
          }
          
          let text = node.nodeValue;
          let changed = false;
          
          // Check for each translation and replace
          for (const [english, french] of Object.entries(translations)) {
            // Use word boundary to replace only whole words
            const regex = new RegExp(`\\b${english}\\b`, 'g');
            if (regex.test(text)) {
              text = text.replace(regex, french);
              changed = true;
            }
          }
          
          // Only update if changes were made
          if (changed) {
            node.nodeValue = text;
          }
        }
      }
      
      // Run initially
      setTimeout(replaceRegistrationText, 1000);
      
      // Set up MutationObserver to detect when Google Translate is done
      const observer = new MutationObserver(function(mutations) {
        // Wait a moment for Google Translate to settle
        setTimeout(replaceRegistrationText, 500);
      });
      
      // Start observing the body for changes
      observer.observe(document.body, { 
        childList: true,
        subtree: true,
        characterData: true
      });
      
      // Also run periodically to catch any missed translations
      setInterval(replaceRegistrationText, 2000);
    });

    // Wait for page to fully load
    document.addEventListener('DOMContentLoaded', function() {
      // Create a variable to track if we've fixed translations
      let translationsFixed = false;
      let fixAttempts = 0;
      const MAX_ATTEMPTS = 5;
      
      // Function to fix specific translations
      function fixTranslations() {
        // Check if page is translated to French
        const isFrench = 
          document.documentElement.lang === 'fr' || 
          document.cookie.indexOf('googtrans=/en/fr') > -1 ||
          document.querySelector('.goog-te-combo')?.value === 'fr';
        
        // If not French, no need to do anything
        if (!isFrench) {
          translationsFixed = false; // Reset so we can fix again if user switches to French
          return;
        }
        
        // If we've already successfully fixed it, don't keep checking
        if (translationsFixed && fixAttempts > MAX_ATTEMPTS) return;
        
        // Increase attempts counter
        fixAttempts++;
        
        // Fix all instances of "Register" - this more thoroughly targets all places it might appear
        const textNodes = [];
        const walker = document.createTreeWalker(
          document.body,
          NodeFilter.SHOW_TEXT,
          null,
          false
        );
        
        let node;
        while (node = walker.nextNode()) {
          // Skip script and style tags
          if (node.parentNode.tagName === 'SCRIPT' || 
              node.parentNode.tagName === 'STYLE') {
            continue;
          }
          
          // Look for text nodes containing "envoyez-vous"
          if (node.nodeValue.toLowerCase().includes('envoyez-vous')) {
            node.nodeValue = node.nodeValue.replace(/envoyez-vous/gi, 'Inscrivez-vous');
          }
        }
        
        // Fix SuccessWay brand in logo
        const logoText = document.querySelector('.logo-text');
        if (logoText) {
          const successSpan = logoText.querySelector('.success');
          const waySpan = logoText.querySelector('.way');
          
          if (successSpan) successSpan.textContent = 'Le chemin';
          if (waySpan) waySpan.textContent = 'du succès';
        }
        
        // Fix SuccessWay in footer copyright
        const footerCopyright = document.querySelector('.copyright');
        if (footerCopyright) {
          const text = footerCopyright.textContent;
          if (text.includes('SuccessWay') || text.includes('Success Way')) {
            footerCopyright.textContent = text.replace(/Success ?Way/gi, 'Le chemin du succès');
          }
        }
        
        // Also fix specific buttons and links
        document.querySelectorAll('a, button, .btn, .dropdown-toggle').forEach(el => {
          if (el.textContent.trim().toLowerCase() === 'envoyez-vous') {
            el.textContent = 'Inscrivez-vous';
          }
        });
        
        // Mark as fixed if we've made a few attempts
        if (fixAttempts >= 3) {
          translationsFixed = true;
        }
      }
      
      // Run once after Google Translate likely finishes
      setTimeout(fixTranslations, 2000);
      
      // Run one more time after a longer period for any late changes
      setTimeout(fixTranslations, 4000);
      
      // Only use MutationObserver initially, then disconnect after translations are fixed
      const observer = new MutationObserver(function(mutations) {
        // Only respond to mutations if not yet fixed
        if (!translationsFixed) {
          fixTranslations();
          
          // Disconnect after we've fixed translations to avoid constant animation
          if (translationsFixed) {
            observer.disconnect();
          }
        }
      });
      
      // Start observing the body for changes
      observer.observe(document.body, { 
        childList: true,
        subtree: true,
        characterData: true
      });
      
      // Add an event listener to the Google Translate dropdown if it exists
      setTimeout(() => {
        const translateCombo = document.querySelector('.goog-te-combo');
        if (translateCombo) {
          translateCombo.addEventListener('change', function() {
            // Reset fix status when language changes
            translationsFixed = false;
            fixAttempts = 0;
            
            // Run fix after a short delay
            setTimeout(fixTranslations, 1000);
            setTimeout(fixTranslations, 2000);
          });
        }
      }, 3000);
    });

    // Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  // Function to fix specific French translations
  function fixFrenchTranslations() {
    // Check if the page is translated to French
    const isFrench = 
      document.documentElement.lang === 'fr' || 
      document.cookie.indexOf('googtrans=/en/fr') > -1 ||
      document.querySelector('.goog-te-combo')?.value === 'fr';
    
    // Only proceed if the page is in French
    if (!isFrench) return;
    
    // Define translations to fix
    const translations = {
      'La voie': 'Le chemin'
    };
    
    // Specific paragraph translations
    const paragraphTranslations = {
      'Le chemin du succès Mali a été fondé en juillet 2018 avec la vision de combler le fossé entre les étudiants maliens et les opportunités éducatives internationales.': 
      'Successway a ouvert ses portes en Août 2018 avec comme vision d\'offrir aux étudiants maliens, un service de qualité en termes de placement universitaire à l\'étranger.',
      
      'Opérations étendues à Ouagadougou, Niamey, Conakry et Abidjan':
      'Création et ouverture de filiales à Ouagadougou, Niamey, Conakry et Abidjan.',
      
      'Établissement de partenariats stratégiques avec des universités au Canada, en Turquie et au-delà':
      'Partenariat stratégique avec établissements au Canada, en Malaisie, Turquie, Inde et Chine.'
    };
    
    // Get all text nodes in the document
    const walker = document.createTreeWalker(
      document.body,
      NodeFilter.SHOW_TEXT,
      null,
      false
    );
    
    // Go through all text nodes
    let node;
    while (node = walker.nextNode()) {
      // Skip script and style tags
      if (node.parentNode.tagName === 'SCRIPT' || 
          node.parentNode.tagName === 'STYLE') {
        continue;
      }
      
      let text = node.nodeValue;
      let changed = false;
      
      // Check for each wrong translation
      for (const [wrong, right] of Object.entries(translations)) {
        if (text.includes(wrong)) {
          text = text.replace(new RegExp(wrong, 'g'), right);
          changed = true;
        }
      }
      
      // Check for the specific paragraphs
      for (const [wrongParagraph, rightParagraph] of Object.entries(paragraphTranslations)) {
        if (text.includes(wrongParagraph)) {
          text = text.replace(wrongParagraph, rightParagraph);
          changed = true;
        }
      }
      
      // Update text if changes were made
      if (changed) {
        node.nodeValue = text;
      }
    }
    
    // Also fix specific elements that might contain the text
    document.querySelectorAll('.logo-text, .footer-brand, .footer-heading, p, div, h4, .new-timeline-content, .timeline-item').forEach(element => {
      let html = element.innerHTML;
      let changed = false;
      
      // Fix individual words/phrases
      for (const [wrong, right] of Object.entries(translations)) {
        if (html.includes(wrong)) {
          html = html.replace(new RegExp(wrong, 'g'), right);
          changed = true;
        }
      }
      
      // Fix specific paragraphs
      for (const [wrongParagraph, rightParagraph] of Object.entries(paragraphTranslations)) {
        if (html.includes(wrongParagraph)) {
          html = html.replace(wrongParagraph, rightParagraph);
          changed = true;
        }
      }
      
      // Update HTML if changes were made
      if (changed) {
        element.innerHTML = html;
      }
    });
  }
  
  // Run the fix initially after a delay to ensure Google Translate has finished
  setTimeout(fixFrenchTranslations, 2000);
  
  // Set up an observer to detect changes to the DOM (like when translation happens)
  const observer = new MutationObserver(function() {
    setTimeout(fixFrenchTranslations, 500);
  });
  
  // Start observing the document
  observer.observe(document.body, { 
    childList: true, 
    subtree: true,
    characterData: true 
  });
  
  // Also run periodically to catch any missed translations
  setInterval(fixFrenchTranslations, 3000);
});
