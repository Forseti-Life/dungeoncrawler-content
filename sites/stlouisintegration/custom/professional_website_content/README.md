# Professional Website Content Module

## Overview
This module creates professional website content based on comprehensive resume data, transforming it into a complete business website with 8+ pages showcasing expertise in AI, FinTech, Healthcare, and Energy sectors.

## Content Created

### Resume Data (54,000+ characters)
- Comprehensive professional profile with major client work
- Detailed experience with Fortune 500 companies:
  - **Citigroup** - Global Investment Banking Division
  - **MasterCard** - Global Processing Network  
  - **Signant Health** - Clinical Trial Technology
  - **AmeriGas UGI** - Energy Distribution Network
  - **NRG Energy** - Grid Modernization Initiative
  - **Edward Jones** - Wealth Management Technology

### Professional Pages
1. **About Us** - Leadership profile with education and credentials
2. **Services** - 6 service categories with detailed offerings
3. **FinTech Solutions** - Industry-specific page with client highlights
4. **Healthcare Solutions** - Clinical trial management and analytics
5. **Energy Solutions** - Smart grid and renewable energy systems
6. **Case Studies** - 3 detailed implementations with quantifiable results
7. **Leadership** - Comprehensive credentials and achievements
8. **Contact** - Enhanced with service offerings and engagement models

### Key Metrics Showcased
- **700+** clinical trials supported
- **$50M+** in fraud prevention savings
- **2.3B** transactions processed monthly
- **99.99%** system uptime SLA
- **30%+** operational efficiency improvements
- **$50M+** total cost savings delivered

## Technical Implementation

### Content Types
- Uses standard Drupal `page` content type
- Professional CSS styling with responsive design
- Bootstrap 5 integration with custom components

### Navigation Structure
- Custom Professional Navigation Block in navbar_left region
- Automated block placement via module installation
- Industry-specific dropdown functionality
- Bootstrap 5 compatible navigation markup
- Professional branding and responsive design

### Block-based Navigation
The module provides a custom navigation block (`professional_navigation_block`) that renders the complete professional website menu:
- **Home** → Front page
- **About Us** → `/about-us` (Node-based with URL alias)
- **Services** → `/services` (Custom route)
- **Industries** (Dropdown) → FinTech, Healthcare, Energy (Custom routes)
- **Case Studies** → `/case-studies` (Node-based with URL alias)
- **Leadership** → `/leadership` (Node-based with URL alias) 
- **Contact** → `/contact` (Node-based with URL alias)

### Styling Features
- Animated background header (preserved from existing design)
- Professional card layouts with hover effects
- Gradient highlight sections for key metrics
- Responsive grid layouts for all content
- Professional color scheme with brand consistency

## Installation

1. Enable the module: `drush en professional_website_content`
2. Module will place navigation block automatically
3. Clear cache: `drush cr`
4. Rebuild theme: `npm run production` in theme directory

**NOTE:** Page creation functions have been removed from the install hook to prevent overwriting manually edited content. Original page creation logic is preserved in `professional_website_content.install.backup` for reference.

## Updates

### Healthcare Page Client Section (Update 9004)
To update existing Healthcare page with standardized Major Clients section:
```bash
cd /var/www/html/stlouisintegration/web && ../vendor/bin/drush --uri=stlouisintegration.com updatedb -y
```

This update adds three healthcare clients in client-grid format matching other industry pages.

### Safe Uninstall Policy
The module implements safe uninstall that preserves all content, blocks, and URL aliases to prevent accidental data loss.

### Page Creation Backup
All original page creation functions are preserved in `professional_website_content.install.backup` file for reference and potential future use.

### Automated Setup
The module installation automatically:
- Creates all professional content pages
- Generates URL aliases (`/about-us`, `/case-studies`, `/leadership`, `/contact`)
- Places the Professional Navigation Block in the `navbar_left` region
- Configures Bootstrap 5 compatible navigation markup

### Manual Block Placement
If needed, the Professional Navigation Block can be manually configured:
1. Go to `/admin/structure/block`
2. Find "Professional Website Navigation" block
3. Place in desired region (recommended: `navbar_left` or `navbar_right`)
4. Configure visibility settings as needed

## Routes Created
- `/services` - Main services page
- `/industries/fintech` - FinTech solutions
- `/industries/healthcare` - Healthcare solutions  
- `/industries/energy` - Energy solutions

## URL Aliases Created
- `/about-us` - About Us page
- `/case-studies` - Case Studies page  
- `/leadership` - Leadership page
- `/contact` - Contact page

## Blocks Created

### Professional Website Navigation Block
- **Professional Website Navigation** - Custom navigation block with dropdown functionality
- **Automatic Placement** - Placed in `navbar_left` region during installation
- **Bootstrap 5 Compatible** - Uses proper Bootstrap navigation classes and structure

### Professional Services Overview Block
- **Professional Services Overview** - Configurable block showcasing key business sections
- **Three Section Layout**: Our Services, Case Studies, Get Started  
- **Responsive Design** - 3-column desktop, stacked mobile layout
- **Professional Styling** - Gradient backgrounds, hover effects, Bootstrap Icons
- **Easy Placement** - Can be placed in any theme region via block admin
- **Call-to-Action Ready** - Direct links to services, case studies, and contact pages

**Block Features:**
- Bootstrap 5 responsive grid system
- Professional card-based design with hover animations  
- Bootstrap Icons integration (bi-gear-fill, bi-bar-chart-line-fill, bi-rocket-takeoff-fill)
- Configurable button text and URLs
- Mobile-optimized responsive breakpoints
- Professional color schemes (primary, success, info)

## Files Modified
- `drupal/web/themes/custom/stlouisintegration/templates/page/page--front.html.twig` - Enhanced navigation
- `drupal/web/themes/custom/stlouisintegration/src/scss/main.style.scss` - Added professional content import
- `drupal/web/themes/custom/stlouisintegration/src/scss/_professional-content.scss` - New professional styling

## Result
Complete transformation from basic animated background site to comprehensive professional business website showcasing real Fortune 500 client work and quantifiable business outcomes.

### Navigation Implementation
- **Block-based Navigation**: Professional menu implemented as reusable Drupal block
- **Automated Setup**: Block placement and URL aliases created during module installation  
- **Bootstrap Integration**: Full Bootstrap 5 dropdown and responsive navigation support
- **SEO-Friendly URLs**: Clean URLs like `/about-us`, `/case-studies`, `/leadership`
- **Maintainable Architecture**: Separates navigation logic from theme templates