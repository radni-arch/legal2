/**
 * Keyboard Navigation Shortcuts
 *
 * Alt+H - Go to Dashboard (Home)
 * Alt+B - Go Back (browser history)
 * Alt+U - Go Up (parent breadcrumb)
 */

document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('keydown', function(e) {
        // Only trigger with Alt key, not in input/textarea
        if (!e.altKey || isTyping(e.target)) {
            return;
        }

        switch(e.key.toLowerCase()) {
            case 'h':
                e.preventDefault();
                window.location.href = '/dashboard';
                break;

            case 'b':
                e.preventDefault();
                window.history.back();
                break;

            case 'u':
                e.preventDefault();
                goToParentBreadcrumb();
                break;
        }
    });
});

function isTyping(element) {
    const tagName = element.tagName.toLowerCase();
    return tagName === 'input' || tagName === 'textarea' || element.isContentEditable;
}

function goToParentBreadcrumb() {
    const breadcrumbs = document.querySelectorAll('nav[aria-label="Breadcrumb"] a');
    if (breadcrumbs.length > 0) {
        const parentLink = breadcrumbs[breadcrumbs.length - 1];
        if (parentLink && parentLink.href) {
            window.location.href = parentLink.href;
        }
    }
}
