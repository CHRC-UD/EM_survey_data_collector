# Survey Data Collector - REDCap External Module

Automatically captures IP addresses (plain or encrypted), user agent strings, browser information, platform details, geographic data, and validates email addresses and phone numbers from survey participants using action tags.

## Features

- **One-Click Field Creation**: Use the Field Manager to add survey data fields with a single click
- **IP Address Collection**: Capture plain text or encrypted IP addresses  
- **Browser Detection**: Automatically detect browser name, version, and platform
- **Bot Detection**: Identify automated bot/crawler submissions
- **Geolocation**: Get city, region, country, timezone, and ISP from IP addresses (via ip-api.com)
- **Email Validation**: Validate email addresses via ZeroBounce API with status, gender, location, and more
- **Phone Validation**: Validate phone numbers via Numverify API with carrier, location, and validity
- **Privacy-Focused**: Encrypted IP storage with key versioning
- **Server-Side Processing**: Data collected server-side after form submission for reliability
- **Debug Mode**: Optional verbose logging for troubleshooting
- **Automatic Configuration**: Fields are automatically hidden on surveys and set to read-only

## Quick Start

### 1. System Setup (Admin Only)

**Control Center → External Modules → Survey Data Collector → Configure**

- **Encryption Key**: Required for encrypted IP collection. Use a strong key (12+ characters, mixed case, numbers).
  - ⚠️ **CRITICAL**: Never change this key once set, or historical data cannot be decrypted
- **Encryption Key Version**: Track key versions (e.g., "v1", "v2") for key rotation

### 2. Project Setup

**Project Home → External Modules → Survey Data Collector → Configure**

#### IP Geolocation Settings
- **Enable IP Geolocation Lookup**: Check to enable geographic data (city, region, country, etc.) per project
  - ⚠️ Sends IPs to ip-api.com third-party service - ensure privacy/IRB compliance
  - Free tier: 1,000 requests/day
  - Default timeout: 3 seconds
- **Geolocation API Timeout**: Maximum wait time for geolocation responses

#### Email Validation (ZeroBounce)
- **ZeroBounce API Key**: Obtain from [zerobounce.net](https://www.zerobounce.net/members/api/)
  - Provides email validity, status, gender, location, and more
  - Leave blank to disable ZeroBounce for this project

#### Phone Validation (Numverify)
- **Numverify API Key**: Obtain from [numverify.com](https://numverify.com/dashboard)
  - Validates phone numbers, returns carrier, line type, location
  - Automatically detects country for 10-digit numbers
  - Leave blank to disable Numverify for this project

#### Debug Settings
- **Debug Mode**: Enable verbose logging for troubleshooting (check Module Logs)
- **Debug: Make IP Field Editable**: Allows testing with different IP values (disable in production)

### 3. Add Fields Using Field Manager

**Project Home → External Modules → Manage Survey Data Fields**

1. Select the instrument where you want to add survey data fields
2. Click the button for the type of data you want to collect
3. Done! The field is automatically created with the correct action tag

**Survey Data Fields (Basic):**
- IP Address (Encrypted) - Privacy-compliant encrypted IP
- IP Address (Plain) - Plain text IP address
- User Agent String - Full browser user agent
- Browser Name - Browser and version (e.g., "Chrome 120")
- Operating System - Platform/OS (e.g., "Windows 10")
- Is Mobile Device - Returns "1" for mobile, "0" for desktop
- Is Robot/Bot - Returns "1" for bots, "0" for humans  
- HTTP Referrer - Page user came from

**Geolocation Fields (ip-api.com):**
- Geolocation Status - "success" or "fail"
- Geolocation Country - Full country name
- Geolocation Country Code - ISO 3166-1 alpha-2 country code
- Geolocation Region - Region/state code
- Geolocation Region Name - Full region/state name
- Geolocation City - City name
- Geolocation Zip - Postal/zip code
- Geolocation Latitude - Geographic latitude
- Geolocation Longitude - Geographic longitude
- Geolocation Timezone - IANA timezone
- Geolocation ISP - Internet Service Provider
- Geolocation Organization - Organization name
- Geolocation AS - AS number and name
- Geolocation Proxy - Proxy/VPN detection (true/false)
- Geolocation Hosting - Hosting/datacenter detection (true/false)

**Email Validation Fields (ZeroBounce):**

First, select an **Email Field** for ZeroBounce to validate against. Then add:
- ZeroBounce Status - Valid/Invalid/Catchall/Unknown/Disposable/Spamtrap
- ZeroBounce Sub-Status - Detailed status code
- ZeroBounce Free Email - Is free email (1/0)
- ZeroBounce Did You Mean - Suggested email if invalid
- ZeroBounce Account - Email username portion
- ZeroBounce Domain - Email domain
- ZeroBounce First Name - From email intelligence
- ZeroBounce Last Name - From email intelligence
- ZeroBounce Gender - From email intelligence
- ZeroBounce City - From email intelligence
- ZeroBounce Region - From email intelligence
- ZeroBounce Country - From email intelligence

**Phone Validation Fields (Numverify):**

First, select a **Phone Field** for Numverify to validate against. Then add:
- Numverify Valid - 1 if valid, 0 if invalid
- Numverify International Format - Phone in international format
- Numverify Local Format - Phone in local format
- Numverify Country Prefix - Country dialing code (e.g., +1)
- Numverify Country Code - ISO country code (e.g., US)
- Numverify Country Name - Full country name
- Numverify Location - City/location from phone number
- Numverify Carrier - Mobile/telecom carrier name
- Numverify Line Type - Mobile/Fixed/Voip type

**What happens when you click "Add Field":**
- Creates a text field at the end of the instrument
- Automatically adds the appropriate action tag (e.g., `@SURVEY-IP`, `@ZEROBOUNCE-STATUS`)
- Adds `@HIDDEN-SURVEY` (hides field on survey pages)
- Adds `@READONLY` (prevents manual editing)
- Auto-generates field name (e.g., `survey_ip_1`, `zb_status_1`)

## Action Tags Reference

If you prefer to manually add fields, use these action tags in the field annotation:

### Survey Data Tags
| Action Tag | Description |
|------------|-------------|
| `@SURVEY-IP-ENCRYPT` | Encrypted IP address (requires encryption key) |
| `@SURVEY-IP` | Plain text IP address |
| `@SURVEY-USER-AGENT` | Full user agent string |
| `@SURVEY-BROWSER` | Browser name and version |
| `@SURVEY-PLATFORM` | Operating system/platform |
| `@SURVEY-IS-MOBILE` | Mobile device indicator (1/0) |
| `@SURVEY-IS-ROBOT` | Bot/crawler indicator (1/0) |
| `@SURVEY-REFERRER` | HTTP referrer URL |

### IP-API Geolocation Tags
| Action Tag | Description |
|------------|-------------|
| `@IPAPI-STATUS` | Geolocation status ("success" or "fail") |
| `@IPAPI-COUNTRY` | Country name |
| `@IPAPI-COUNTRY-CODE` | ISO country code |
| `@IPAPI-REGION` | Region/state code |
| `@IPAPI-REGION-NAME` | Full region/state name |
| `@IPAPI-CITY` | City name |
| `@IPAPI-ZIP` | Postal/zip code |
| `@IPAPI-LAT` | Geographic latitude |
| `@IPAPI-LON` | Geographic longitude |
| `@IPAPI-TIMEZONE` | IANA timezone |
| `@IPAPI-ISP` | Internet Service Provider |
| `@IPAPI-ORG` | Organization name |
| `@IPAPI-AS` | AS number and name |
| `@IPAPI-PROXY` | Proxy/VPN detection (true/false) |
| `@IPAPI-HOSTING` | Hosting/datacenter detection (true/false) |

### ZeroBounce Email Validation Tags
| Action Tag | Description |
|------------|-------------|
| `@ZEROBOUNCE-STATUS` | Email validity status |
| `@ZEROBOUNCE-SUB-STATUS` | Detailed status code |
| `@ZEROBOUNCE-FREE-EMAIL` | Is free email provider (1/0) |
| `@ZEROBOUNCE-DID-YOU-MEAN` | Suggested email if invalid |
| `@ZEROBOUNCE-ACCOUNT` | Email username |
| `@ZEROBOUNCE-DOMAIN` | Email domain |
| `@ZEROBOUNCE-FIRSTNAME` | First name from intelligence |
| `@ZEROBOUNCE-LASTNAME` | Last name from intelligence |
| `@ZEROBOUNCE-GENDER` | Gender from intelligence |
| `@ZEROBOUNCE-CITY` | City from intelligence |
| `@ZEROBOUNCE-REGION` | Region from intelligence |
| `@ZEROBOUNCE-COUNTRY` | Country from intelligence |

### Numverify Phone Validation Tags
| Action Tag | Description |
|------------|-------------|
| `@NUMVERIFY-VALID` | Is valid phone number (1/0) |
| `@NUMVERIFY-INTERNATIONAL` | Phone in international format |
| `@NUMVERIFY-LOCAL` | Phone in local format |
| `@NUMVERIFY-COUNTRY-PREFIX` | Country dialing code |
| `@NUMVERIFY-COUNTRY-CODE` | ISO country code |
| `@NUMVERIFY-COUNTRY-NAME` | Full country name |
| `@NUMVERIFY-LOCATION` | Location from phone number |
| `@NUMVERIFY-CARRIER` | Carrier/telecom name |
| `@NUMVERIFY-LINE-TYPE` | Mobile/Fixed/Voip |

**Recommended usage:** Combine with `@HIDDEN-SURVEY` and `@READONLY`
```
Field Annotation: @SURVEY-IP @HIDDEN-SURVEY @READONLY
Field Annotation: @ZEROBOUNCE-STATUS @HIDDEN-SURVEY @READONLY
Field Annotation: @NUMVERIFY-VALID @HIDDEN-SURVEY @READONLY
```

## How It Works

### Survey Data Collection (IP, Browser, Geolocation)
1. **Survey Submission**: When participant submits survey, the `redcap_survey_complete` hook fires
2. **Data Gathering**: Module collects IP address, user agent, browser info, and optionally geolocation
3. **Automatic Population**: Fields with action tags are populated with collected data
4. **Data Storage**: All data saved via REDCap::saveData() in a single transaction

### Email Validation (ZeroBounce)
1. **Survey Submission**: Module retrieves email from the configured email field
2. **API Validation**: Sends email to ZeroBounce API for validation
3. **Field Population**: ZeroBounce response fields populated with status, name, location, etc.
4. **Data Storage**: Email validation results saved with survey data

### Phone Validation (Numverify)
1. **Survey Submission**: Module retrieves phone from the configured phone field
2. **Phone Normalization**: Detects 10-digit US/Canada numbers and prepends country code (+1)
3. **API Validation**: Sends phone to Numverify API for validation
4. **Field Population**: Numverify response fields populated with format, carrier, location, etc.
5. **Data Storage**: Phone validation results saved with survey data

**NAT/Proxy Behavior**: The module captures the public IP address after NAT translation. Multiple users behind the same NAT device (office, home network) will show the same IP address.

## Decrypting Encrypted IPs

**Control Center → External Modules → IP-DECRYPT**

- Enter an encrypted IP value from your data
- Decrypted value is displayed (not stored)
- All decryption attempts are logged

⚠️ **Privacy Warning**: IP decryption may require IRB approval. Only authorized administrators should access this tool.

## Debugging

### Enable Debug Mode
Project settings: Check **"Debug Mode"** to enable verbose logging

### View Debug Logs
**Control Center → Logging** and filter for "Survey Data Collector" module

### Debug Log Output
When debug mode enabled, logs will show:
- Hook execution parameters (record, instrument, event)
- Action tag configs found
- API key presence and field mappings
- Each field added with its value
- API requests and responses (ZeroBounce, Numverify)
- Data save results

### Test with Editable IP Field
Project settings: Check **"Debug: Make IP Field Editable"** to test with different IP values
- Field will be visible on survey
- Can manually enter test values
- Useful for testing geolocation, email validation, etc.

## Privacy & Compliance

### IP Address Collection
- Plain text IPs may have privacy/GDPR implications
- Use encrypted IPs when possible for compliance
- Ensure participant consent covers data collection
- Document IP usage in your IRB protocol

### Geolocation
- Sends IPs to ip-api.com (third-party service)
- Ensure IRB approval and participant consent
- City-level accuracy (not precise location)
- Can be toggled per-project
- Free tier: 45,000 requests/month, 120 requests/minute
- For higher volume, see [ip-api.com pricing](https://ip-api.com/pricing)

### Email Validation (ZeroBounce)
- Sends email addresses to ZeroBounce API
- Ensure IRB approval and participant consent
- ZeroBounce does NOT store data (see their privacy policy)
- Can be disabled per-project by leaving API key blank

### Phone Validation (Numverify)
- Sends phone numbers to Numverify API
- Ensure IRB approval and participant consent
- Numverify does NOT store data (see their privacy policy)
- Can be disabled per-project by leaving API key blank

### Data Retention
- Plan for encrypted key storage and retention
- Document key rotation procedures
- Maintain decryption key access controls
- Consider data deletion timelines per your IRB

## Troubleshooting

### Fields Not Populating
1. Verify module is enabled in project
2. Enable **Debug Mode** in project settings
3. Check module logs (Control Center → Logging)
4. Confirm field has action tag in annotation
5. Test on survey page (not data entry form)
6. Verify field is text type
7. Check that email/phone fields are populated (for ZeroBounce/Numverify)

### Geolocation Not Working
1. Verify **"Enable IP Geolocation Lookup"** is checked in project settings
2. Check rate limits (45,000/month or 120/minute on free tier at ip-api.com)
3. Review module logs for API errors (HTTP 429 = rate limit exceeded)
4. Verify timeout setting is reasonable (3-10 seconds)
5. If rate limited, consider upgrading to a paid plan at [ip-api.com](https://ip-api.com/pricing)

### Email Validation Not Working
1. Verify **ZeroBounce API Key** is entered in project settings
2. Verify **Email Field for ZeroBounce** is selected
3. Check that email field is populated in survey
4. Review module logs for ZeroBounce API errors
5. Verify API key is valid at zerobounce.net

### Phone Validation Not Working
1. Verify **Numverify API Key** is entered in project settings
2. Verify **Phone Field for Numverify** is selected (deprecated - now auto-detects)
3. Check that phone field is populated in survey
4. Review module logs for Numverify API errors
5. Verify API key is valid at numverify.com

### Encrypted IPs Won't Decrypt
1. Verify encryption key hasn't changed
2. Check key version matches the encrypted value
3. Review decryption logs for error messages

## Support & Documentation

- **Module Logs**: Control Center → Logging (filter by "Survey Data Collector")
- **Debug Mode**: Enable in project settings for detailed troubleshooting logs
- **REDCap Community**: [community.projectredcap.org](https://community.projectredcap.org)

## License

MIT License - See LICENSE file for details

## Credits

- **Browser Detection**: Wolfcast Library (BrowserDetection class)
- **Action Tag Parsing**: REDCap External Module Framework
- **Geolocation**: ip-api.com API
- **Email Validation**: ZeroBounce API
- **Phone Validation**: Numverify API

## Changelog

### v1.0.0
- Initial release
- Action tag-based data collection
- Field Manager for one-click field creation
- IP encryption with key versioning
- Geolocation integration (ipapi.co)
- Browser and platform detection
- Bot detection
- Admin decryption tool
- ZeroBounce email validation integration
- Numverify phone validation integration
- Server-side data population via redcap_survey_complete hook
- Project-level configuration for geolocation, ZeroBounce, Numverify
- Debug mode with verbose logging
- Editable IP field for testing
- Comprehensive error logging and troubleshooting support

---

**Last Updated**: January 2026
**Module Version**: 1.0.0
**REDCap Minimum Version**: 13.1.0
