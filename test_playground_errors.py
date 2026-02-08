#!/usr/bin/env python3
"""
Systematically test the Legal Playground to discover all errors.
"""
from playwright.sync_api import sync_playwright
import json
import sys

def test_playground():
    errors = []

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Capture console errors
        console_messages = []
        page.on('console', lambda msg: console_messages.append({
            'type': msg.type,
            'text': msg.text,
            'location': msg.location
        }))

        # Capture page errors
        page_errors = []
        page.on('pageerror', lambda err: page_errors.append({
            'message': str(err),
            'stack': err.stack if hasattr(err, 'stack') else None
        }))

        # Capture failed requests
        failed_requests = []
        page.on('requestfailed', lambda req: failed_requests.append({
            'url': req.url,
            'method': req.method,
            'failure': req.failure
        }))

        try:
            print("🔍 Navigating to /playground...")
            response = page.goto('http://localhost:8000/playground', wait_until='networkidle', timeout=30000)

            if response and response.status >= 400:
                errors.append({
                    'type': 'HTTP_ERROR',
                    'status': response.status,
                    'url': response.url,
                    'message': f'Playground returned HTTP {response.status}'
                })

            print(f"✅ Page loaded with status: {response.status if response else 'unknown'}")

            # Take initial screenshot
            page.screenshot(path='/tmp/playground_initial.png', full_page=True)
            print("📸 Initial screenshot saved to /tmp/playground_initial.png")

            # Wait a bit for any dynamic content
            page.wait_for_timeout(2000)

            # Discover all interactive elements
            print("\n🔍 Discovering interactive elements...")
            buttons = page.locator('button').all()
            print(f"   Found {len(buttons)} buttons")

            tabs = page.locator('[role="tab"]').all()
            print(f"   Found {len(tabs)} tabs")

            links = page.locator('a').all()
            print(f"   Found {len(links)} links")

            forms = page.locator('form').all()
            print(f"   Found {len(forms)} forms")

            # Click through each tab/section
            print("\n🖱️  Testing tabs/sections...")
            for i, tab in enumerate(tabs):
                try:
                    if tab.is_visible():
                        tab_text = tab.inner_text()
                        print(f"   Clicking tab {i+1}: {tab_text[:50]}")
                        tab.click()
                        page.wait_for_timeout(1000)
                        page.screenshot(path=f'/tmp/playground_tab_{i+1}.png', full_page=True)
                except Exception as e:
                    errors.append({
                        'type': 'TAB_CLICK_ERROR',
                        'tab_index': i,
                        'message': str(e)
                    })
                    print(f"   ❌ Error clicking tab {i+1}: {e}")

            # Try clicking main action buttons
            print("\n🖱️  Testing buttons...")
            for i, button in enumerate(buttons):
                try:
                    if button.is_visible():
                        button_text = button.inner_text() or button.get_attribute('aria-label') or f"Button {i+1}"
                        print(f"   Testing button: {button_text[:50]}")

                        # Skip if disabled
                        if button.get_attribute('disabled'):
                            print(f"   ⏭️  Skipped (disabled)")
                            continue

                        button.click()
                        page.wait_for_timeout(1000)

                except Exception as e:
                    errors.append({
                        'type': 'BUTTON_CLICK_ERROR',
                        'button_index': i,
                        'button_text': button_text[:50] if 'button_text' in locals() else f"Button {i+1}",
                        'message': str(e)
                    })
                    print(f"   ❌ Error: {e}")

            # Test form submissions (if any visible forms)
            print("\n📝 Testing forms...")
            for i, form in enumerate(forms):
                try:
                    if form.is_visible():
                        print(f"   Found form {i+1}")
                        # Try to find submit buttons within the form
                        submit_buttons = form.locator('button[type="submit"], input[type="submit"]').all()
                        for j, submit_btn in enumerate(submit_buttons):
                            if submit_btn.is_visible() and not submit_btn.get_attribute('disabled'):
                                print(f"   Clicking submit button {j+1} in form {i+1}")
                                submit_btn.click()
                                page.wait_for_timeout(1000)
                except Exception as e:
                    errors.append({
                        'type': 'FORM_SUBMIT_ERROR',
                        'form_index': i,
                        'message': str(e)
                    })
                    print(f"   ❌ Form error: {e}")

            # Final screenshot
            page.screenshot(path='/tmp/playground_final.png', full_page=True)
            print("\n📸 Final screenshot saved to /tmp/playground_final.png")

        except Exception as e:
            errors.append({
                'type': 'FATAL_ERROR',
                'message': str(e),
                'stack': getattr(e, 'stack', None)
            })
            print(f"\n❌ Fatal error: {e}")

        finally:
            browser.close()

        # Compile all errors
        all_errors = {
            'interaction_errors': errors,
            'console_errors': [m for m in console_messages if m['type'] == 'error'],
            'console_warnings': [m for m in console_messages if m['type'] == 'warning'],
            'page_errors': page_errors,
            'failed_requests': failed_requests
        }

        return all_errors

if __name__ == '__main__':
    print("=" * 70)
    print("🧪 Legal Playground Error Discovery")
    print("=" * 70)

    all_errors = test_playground()

    # Print summary
    print("\n" + "=" * 70)
    print("📊 ERROR SUMMARY")
    print("=" * 70)

    total_errors = (
        len(all_errors['interaction_errors']) +
        len(all_errors['console_errors']) +
        len(all_errors['page_errors']) +
        len(all_errors['failed_requests'])
    )

    print(f"\n🔴 Interaction Errors: {len(all_errors['interaction_errors'])}")
    for err in all_errors['interaction_errors']:
        print(f"   - {err['type']}: {err['message'][:100]}")

    print(f"\n🔴 Console Errors: {len(all_errors['console_errors'])}")
    for err in all_errors['console_errors'][:10]:  # Limit to first 10
        print(f"   - {err['text'][:100]}")

    print(f"\n⚠️  Console Warnings: {len(all_errors['console_warnings'])}")
    for err in all_errors['console_warnings'][:5]:  # Limit to first 5
        print(f"   - {err['text'][:100]}")

    print(f"\n🔴 Page Errors: {len(all_errors['page_errors'])}")
    for err in all_errors['page_errors']:
        print(f"   - {err['message'][:100]}")

    print(f"\n🔴 Failed Requests: {len(all_errors['failed_requests'])}")
    for req in all_errors['failed_requests']:
        print(f"   - {req['method']} {req['url'][:80]}")

    print(f"\n{'='*70}")
    print(f"🎯 TOTAL ERRORS FOUND: {total_errors}")
    print(f"{'='*70}\n")

    # Save detailed report
    report_path = '/tmp/playground_error_report.json'
    with open(report_path, 'w') as f:
        json.dump(all_errors, f, indent=2)
    print(f"📄 Detailed report saved to: {report_path}")

    sys.exit(0 if total_errors == 0 else 1)
