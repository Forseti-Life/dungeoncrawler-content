<?php

namespace Drupal\professional_website_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for professional website content pages.
 */
class ProfessionalContentController extends ControllerBase {

  /**
   * Display the home page.
   */
  public function homePage() {
    return [
      '#markup' => '
<article class="node node--type-page node--promoted node--sticky node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Welcome to St. Louis Integration</span></h1>
    
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>

    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
                <div class="hero-section text-center py-5 mb-5">
                    <div class="container">
                        <h1 class="display-4 mb-4">
                            Transform Your Business with AI &amp; Automation
                        </h1>
                        <p class="lead mb-4">
                            St. Louis Integration provides cutting-edge technology solutions to help businesses automate processes, integrate AI capabilities, and achieve digital transformation.
                        </p>
                    </div>
                </div>
                <div class="container">
                    <div class="row mb-5">
                        <div class="col-md-4 text-center mb-4">
                            <div class="feature-box p-4">
                                <h3>
                                    AI Integration
                                </h3>
                                <p>
                                    Leverage artificial intelligence to automate tasks, improve decision-making, and enhance customer experiences.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="feature-box p-4">
                                <h3>
                                    System Integration
                                </h3>
                                <p>
                                    Connect disparate systems and streamline your business processes for maximum efficiency.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="feature-box p-4">
                                <h3>
                                    Digital Transformation
                                </h3>
                                <p>
                                    Modernize your business with comprehensive digital transformation strategies and implementation.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the about page.
   */
  public function aboutPage() {
    return [
      '#markup' => '
<article data-history-node-id="29" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>About Us</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="about-us-section">
    <div class="row">
        <div class="col-lg-8">
            <h2>
                About St. Louis Integration
            </h2>
            <p class="lead">
                St. Louis Integration is a premier technology consulting firm specializing in AI-powered solutions, enterprise automation, and digital transformation across Financial Services, Healthcare, and Energy sectors.
            </p>
            <p>
                Founded on the principle that technology should drive measurable business outcomes, we partner with Fortune 500 companies to implement cutting-edge solutions that deliver operational efficiency improvements of 30%+ and cost savings exceeding $50 million.
            </p>
            <h3>
                Our Expertise
            </h3>
            <div class="expertise-grid">
                <div class="expertise-item">
                    <h4>
                        Financial Services
                    </h4>
                    <p>
                        Advanced algorithmic trading platforms, fraud detection systems, risk management solutions, and regulatory compliance automation.
                    </p>
                </div>
                <div class="expertise-item">
                    <h4>
                        Healthcare Technology
                    </h4>
                    <p>
                        Clinical trial management systems, patient data analytics, regulatory compliance platforms supporting 700+ active studies.
                    </p>
                </div>
                <div class="expertise-item">
                    <h4>
                        Energy &amp; Utilities
                    </h4>
                    <p>
                        Smart grid implementations, renewable energy integration, predictive maintenance, and supply chain optimization.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="leadership-profile">
                <h3>
                    Leadership
                </h3>
                <div class="leader-card">
                    <h4>
                        Keith Aumiller
                    </h4>
                    <p class="title">
                        Founder &amp; Principal Consultant
                    </p>
                    <ul>
                        <li>
                            MBA Washington University in St. Louis (AI Focus)
                        </li>
                        <li>
                            BS Psychology Truman State University
                        </li>
                        <li>
                            25+ years enterprise consulting experience
                        </li>
                        <li>
                            AWS Solutions Architect - Professional
                        </li>
                        <li>
                            Google Cloud Professional - Machine Learning Engineer
                        </li>
                        <li>
                            Microsoft Azure - AI Engineer
                        </li>
                        <li>
                            PMI Project Management - Professional (PMP)
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span', 'br'],
    ];
  }

  /**
   * Display the services page.
   */
  public function servicesPage() {
    return [
      '#markup' => '
<article data-history-node-id="100" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Services</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
                <div class="services-section">
                  <h2>Our Services</h2>
                  <p class="lead">Comprehensive technology solutions designed to transform your business operations and drive measurable results.</p>
                  
                  <div class="services-grid row">
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3>AI & Machine Learning</h3>
                        <ul>
                          <li>Predictive analytics and forecasting</li>
                          <li>Deep learning model development</li>
                          <li>Natural language processing</li>
                          <li>Computer vision solutions</li>
                          <li>Automated decision systems</li>
                        </ul>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3>Financial Technology</h3>
                        <ul>
                          <li>Algorithmic trading platforms</li>
                          <li>Fraud detection systems</li>
                          <li>Risk management solutions</li>
                          <li>Regulatory compliance automation</li>
                          <li>Portfolio optimization tools</li>
                        </ul>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3>Healthcare Technology</h3>
                        <ul>
                          <li>Clinical trial management</li>
                          <li>Patient data analytics</li>
                          <li>Regulatory compliance systems</li>
                          <li>Medical device integration</li>
                          <li>Healthcare workflow optimization</li>
                        </ul>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3>Energy & Utilities</h3>
                        <ul>
                          <li>Smart grid implementation</li>
                          <li>Renewable energy integration</li>
                          <li>Predictive maintenance</li>
                          <li>Supply chain optimization</li>
                          <li>Demand forecasting</li>
                        </ul>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3>Enterprise Architecture</h3>
                        <ul>
                          <li>Cloud-native solutions</li>
                          <li>Microservices architecture</li>
                          <li>API integration platforms</li>
                          <li>Data architecture design</li>
                          <li>Infrastructure automation</li>
                        </ul>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3>Digital Transformation</h3>
                        <ul>
                          <li>Process automation</li>
                          <li>Legacy system modernization</li>
                          <li>Change management</li>
                          <li>Technology strategy consulting</li>
                          <li>Performance optimization</li>
                        </ul>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3><a href="/services/ai-machine-learning">AI & Machine Learning</a></h3>
                        <ul>
                          <li>Custom AI solutions and generative AI</li>
                          <li>Deep learning frameworks (TensorFlow, PyTorch)</li>
                          <li>MLOps and automated model deployment</li>
                          <li>Statistical modeling and predictive analytics</li>
                          <li>Computer vision and NLP solutions</li>
                        </ul>
                        <p class="service-cta"><a href="/services/ai-machine-learning" class="btn btn-outline-primary btn-sm">Learn More</a></p>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3><a href="/services/data-engineering-architecture">Data Engineering & Architecture</a></h3>
                        <ul>
                          <li>Cloud-native platforms (AWS, Azure, GCP)</li>
                          <li>Enterprise data architecture and data lakes</li>
                          <li>Real-time processing and API strategy</li>
                          <li>Databricks, Snowflake, and modern data stack</li>
                          <li>Streaming pipelines and data governance</li>
                        </ul>
                        <p class="service-cta"><a href="/services/data-engineering-architecture" class="btn btn-outline-primary btn-sm">Learn More</a></p>
                      </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-6">
                      <div class="service-card">
                        <h3><a href="/services/product-prototyping">Product Prototyping</a></h3>
                        <ul>
                          <li>Rapid 2-week development</li>
                          <li>Any technology product</li>
                          <li>Large or small companies</li>
                          <li>Flexible pricing model</li>
                          <li>Proven track record</li>
                        </ul>
                        <p class="service-cta"><a href="/services/product-prototyping" class="btn btn-outline-primary btn-sm">Learn More</a></p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the FinTech solutions page.
   */
  public function fintechPage() {
    return [
      '#markup' => '
<article data-history-node-id="31" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>FinTech Solutions</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="industry-page fintech">
  <h2>Financial Services Technology</h2>
  <p class="lead">Transforming financial services through advanced AI, algorithmic trading, and regulatory compliance solutions.</p>
  
  <div class="industry-highlights">
    <div class="row">
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>$50M+</h3>
          <p>Fraud Prevention Savings</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>2.3B</h3>
          <p>Transactions Processed Monthly</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>99.99%</h3>
          <p>System Uptime SLA</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="solutions-section">
    <h3>Our FinTech Solutions</h3>
    <div class="row">
      <div class="col-lg-6">
        <h4>Algorithmic Trading</h4>
        <p>High-frequency trading platforms with machine learning-enhanced risk management, processing millions of transactions daily with sub-millisecond latency.</p>
        <ul>
          <li>Real-time market data processing</li>
          <li>Predictive analytics for price movements</li>
          <li>Automated risk assessment</li>
          <li>Portfolio optimization algorithms</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <h4>Fraud Detection</h4>
        <p>AI-powered fraud prevention systems that analyze transaction patterns in real-time, reducing false positives while maintaining security.</p>
        <ul>
          <li>Behavioral analysis algorithms</li>
          <li>Real-time transaction scoring</li>
          <li>Adaptive learning models</li>
          <li>Compliance reporting automation</li>
        </ul>
      </div>
    </div>
  </div>
  
  <div class="client-section">
    <h3>Major Clients</h3>
    <div class="client-grid">
      <div class="client-card">
        <h4>Citigroup</h4>
        <p>Global Investment Banking Division - Algorithmic trading platform with ML risk management</p>
      </div>
      <div class="client-card">
        <h4>MasterCard</h4>
        <p>Global Processing Network - Enhanced fraud prevention and real-time analytics</p>
      </div>
      <div class="client-card">
        <h4>Edward Jones</h4>
        <p>Wealth Management - AI-powered investment recommendations and portfolio optimization</p>
      </div>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the Healthcare solutions page.
   */
  public function healthcarePage() {
    return [
      '#markup' => '
<article data-history-node-id="105" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Healthcare Solutions</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="industry-page healthcare">
  <h2>Healthcare Technology Solutions</h2>
  <p class="lead">Advancing healthcare through clinical trial optimization, patient data analytics, and regulatory compliance systems.</p>
  
  <div class="industry-highlights">
    <div class="row">
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>700+</h3>
          <p>Clinical Trials Supported</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>$2.1B</h3>
          <p>Drug Approvals Supported</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>35%</h3>
          <p>Patient Retention Improvement</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="solutions-section">
    <h3>Our Healthcare Solutions</h3>
    <div class="row">
      <div class="col-lg-6">
        <h4>Clinical Trial Management</h4>
        <p>Comprehensive platforms that optimize every aspect of clinical trials from patient recruitment to regulatory reporting.</p>
        <ul>
          <li>AI-powered patient recruitment</li>
          <li>Predictive analytics for trial success</li>
          <li>Real-time trial monitoring</li>
          <li>Automated regulatory reporting</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <h4>Patient Data Analytics</h4>
        <p>Advanced analytics platforms that provide insights into patient outcomes and treatment effectiveness.</p>
        <ul>
          <li>Predictive outcome modeling</li>
          <li>Treatment efficacy analysis</li>
          <li>Population health insights</li>
          <li>Real-world evidence generation</li>
        </ul>
      </div>
    </div>
  </div>
  
  <div class="client-section">
    <h3>Major Clients</h3>
    <div class="client-grid">
      <div class="client-card">
        <h4>Signant Health</h4>
        <p>Clinical Trial Technology - Modernized platform serving 700+ active studies with AI-powered optimization</p>
      </div>
      <div class="client-card">
        <h4>Centers for Disease Control</h4>
        <p>Public Health Analytics - National health surveillance systems and epidemic response platforms</p>
      </div>
      <div class="client-card">
        <h4>AbbVie</h4>
        <p>Pharmaceutical Research - Clinical data management and regulatory compliance systems</p>
      </div>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the Energy solutions page.
   */
  public function energyPage() {
    return [
      '#markup' => '
<article data-history-node-id="106" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Energy Solutions</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="industry-page energy">
  <h2>Energy & Utilities Technology</h2>
  <p class="lead">Powering the future with smart grid technologies, renewable energy integration, and predictive maintenance solutions.</p>
  
  <div class="industry-highlights">
    <div class="row">
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>99.97%</h3>
          <p>Grid Reliability Achievement</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>500MW+</h3>
          <p>Renewable Capacity Managed</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="highlight-card">
          <h3>42%</h3>
          <p>Unplanned Outage Reduction</p>
        </div>
      </div>
    </div>
  </div>
  
  <div class="solutions-section">
    <h3>Our Energy Solutions</h3>
    <div class="row">
      <div class="col-lg-6">
        <h4>Smart Grid Implementation</h4>
        <p>Advanced IoT sensor networks and AI-powered analytics for optimized energy distribution and grid management.</p>
        <ul>
          <li>Real-time grid monitoring</li>
          <li>Load forecasting and optimization</li>
          <li>Predictive maintenance algorithms</li>
          <li>Automated fault detection</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <h4>Supply Chain Optimization</h4>
        <p>AI-driven demand forecasting and logistics optimization for energy distribution networks.</p>
        <ul>
          <li>Seasonal demand prediction</li>
          <li>Route optimization algorithms</li>
          <li>Inventory management systems</li>
          <li>Fleet optimization platforms</li>
        </ul>
      </div>
    </div>
  </div>
  
  <div class="client-section">
    <h3>Major Clients</h3>
    <div class="client-grid">
      <div class="client-card">
        <h4>NRG Energy</h4>
        <p>Grid Modernization - Smart grid technologies and renewable energy integration platform</p>
      </div>
      <div class="client-card">
        <h4>AmeriGas UGI</h4>
        <p>Distribution Network - AI-driven demand forecasting and logistics optimization</p>
      </div>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the Product Prototyping page.
   */
  public function productPrototypingPage() {
    return [
      '#markup' => '
<article data-history-node-id="101" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title">
<span>Product Prototyping</span>
</h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="product-prototyping-page">
  <div class="hero-section">
    <h1>Product Prototyping</h1>
    <p class="lead">From Concept to Reality in Just 2 Weeks</p>
  </div>
  
  <div class="main-content">
    <div class="row">
      <div class="col-lg-8">
        <h2>Build Any Technology Product Your Company Needs</h2>
        <p>Whether you need a simple web application or a complex enterprise solution, we can build any technology product your company requires. Our rapid prototyping process ensures you get from concept to working product in just 2 weeks, regardless of company size.</p>
        
        <div class="key-benefits">
          <h3>Why Choose Our Product Prototyping?</h3>
          <ul class="benefit-list">
            <li><strong>Lightning Fast:</strong> 2-week turnaround from concept to working prototype</li>
            <li><strong>Any Scale:</strong> Perfect for large enterprises and small startups alike</li>
            <li><strong>Full Stack:</strong> Web applications, mobile apps, APIs, databases, and integrations</li>
            <li><strong>Proven Results:</strong> See our live example at <a href="https://thetruthperspective.org" target="_blank" rel="noopener">The Truth Perspective</a></li>
            <li><strong>Flexible Pricing:</strong> Costs tailored to your level of commitment and complexity</li>
          </ul>
        </div>
        
        <div class="example-showcase">
          <h3>Real Example: The Truth Perspective</h3>
          <p>Visit <a href="https://thetruthperspective.org" target="_blank" rel="noopener" class="btn btn-primary">thetruthperspective.org</a> to see a live example of our rapid prototyping capabilities. This comprehensive news and analysis platform showcases our ability to build feature-rich, scalable applications quickly.</p>
        </div>
        
        <div class="process-overview">
          <h3>Our 2-Week Process</h3>
          <div class="row">
            <div class="col-md-6">
              <div class="process-step">
                <h4>Week 1: Foundation</h4>
                <ul>
                  <li>Requirements analysis</li>
                  <li>Architecture design</li>
                  <li>Core functionality development</li>
                  <li>Database setup</li>
                </ul>
              </div>
            </div>
            <div class="col-md-6">
              <div class="process-step">
                <h4>Week 2: Polish & Deploy</h4>
                <ul>
                  <li>UI/UX implementation</li>
                  <li>Testing & optimization</li>
                  <li>Integration setup</li>
                  <li>Deployment & handoff</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4">
        <div class="pricing-sidebar">
          <h3>Flexible Pricing</h3>
          <p>Our pricing model adapts to your specific needs:</p>
          
          <div class="pricing-factors">
            <h4>Pricing Factors:</h4>
            <ul>
              <li><strong>Complexity Level:</strong> Simple to enterprise-grade</li>
              <li><strong>Feature Count:</strong> Basic to comprehensive</li>
              <li><strong>Integration Needs:</strong> Standalone to multi-system</li>
              <li><strong>Commitment Level:</strong> One-time to ongoing partnership</li>
            </ul>
          </div>
          
          <div class="cta-section">
            <h4>Ready to Get Started?</h4>
            <p>Contact us today to discuss your project and get a customized quote.</p>
            <a href="/contact-us" class="btn btn-primary btn-lg">Get Your Quote</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em'],
    ];
  }

  /**
   * Display the AI & Machine Learning page.
   */
  public function aiMachineLearningPage() {
    return [
      '#markup' => '
<article data-history-node-id="102" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>AI & Machine Learning</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
                <div class="ai-machine-learning-page">
                  <div class="hero-section">
                    <h1>AI & Machine Learning</h1>
                    <p class="lead">Custom AI Solutions and Generative AI Implementation</p>
                  </div>
                  
                  <div class="main-content">
                    <div class="row">
                      <div class="col-lg-8">
                        <h2>Core Technology Capabilities</h2>
                        <p>We deliver cutting-edge AI and machine learning solutions that transform how businesses operate, make decisions, and serve their customers. Our expertise spans the entire AI ecosystem, from research and development to production deployment.</p>
                        
                        <div class="capabilities-grid">
                          <div class="row">
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>Custom AI Solutions</h3>
                                <ul>
                                  <li>Generative AI implementation</li>
                                  <li>Large Language Model (LLM) integration</li>
                                  <li>Computer vision applications</li>
                                  <li>Natural language processing</li>
                                  <li>Conversational AI and chatbots</li>
                                </ul>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>Deep Learning Frameworks</h3>
                                <ul>
                                  <li>TensorFlow and PyTorch expertise</li>
                                  <li>Neural network architecture design</li>
                                  <li>Model optimization and fine-tuning</li>
                                  <li>Transfer learning implementation</li>
                                  <li>Custom algorithm development</li>
                                </ul>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>MLOps & Deployment</h3>
                                <ul>
                                  <li>Automated model deployment pipelines</li>
                                  <li>Model monitoring and maintenance</li>
                                  <li>A/B testing for ML models</li>
                                  <li>Continuous integration/deployment</li>
                                  <li>Scalable inference architecture</li>
                                </ul>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>Statistical Modeling</h3>
                                <ul>
                                  <li>Predictive analytics implementation</li>
                                  <li>Time series forecasting</li>
                                  <li>Statistical analysis and modeling</li>
                                  <li>Risk assessment algorithms</li>
                                  <li>Business intelligence solutions</li>
                                </ul>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="industry-applications">
                          <h3>Industry Applications</h3>
                          <p>Our AI solutions have been successfully deployed across various sectors:</p>
                          <ul class="industry-list">
                            <li><strong>Financial Services:</strong> Fraud detection, algorithmic trading, risk management</li>
                            <li><strong>Healthcare:</strong> Medical imaging analysis, patient outcome prediction, clinical decision support</li>
                            <li><strong>Energy:</strong> Demand forecasting, predictive maintenance, grid optimization</li>
                            <li><strong>Retail:</strong> Recommendation engines, inventory optimization, customer analytics</li>
                          </ul>
                        </div>
                      </div>
                      
                      <div class="col-lg-4">
                        <div class="technology-sidebar">
                          <h3>Technology Stack</h3>
                          <div class="tech-categories">
                            <h4>Frameworks & Libraries</h4>
                            <ul>
                              <li>TensorFlow / Keras</li>
                              <li>PyTorch / Lightning</li>
                              <li>scikit-learn</li>
                              <li>Hugging Face Transformers</li>
                              <li>OpenAI API</li>
                            </ul>
                            
                            <h4>Cloud Platforms</h4>
                            <ul>
                              <li>AWS SageMaker</li>
                              <li>Azure Machine Learning</li>
                              <li>Google AI Platform</li>
                              <li>Databricks ML</li>
                            </ul>
                            
                            <h4>Languages & Tools</h4>
                            <ul>
                              <li>Python, R, Julia</li>
                              <li>Docker & Kubernetes</li>
                              <li>MLflow, Weights & Biases</li>
                              <li>Apache Spark</li>
                            </ul>
                          </div>
                          
                          <div class="cta-section mt-4">
                            <h4>Ready to Implement AI?</h4>
                            <p>Let\'s discuss how AI can transform your business operations.</p>
                            <a href="/contact-us" class="btn btn-primary btn-lg">Get Started</a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the Data Engineering & Architecture page.
   */
  public function dataEngineeringPage() {
    return [
      '#markup' => '
<article data-history-node-id="103" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Data Engineering & Architecture</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
                <div class="data-engineering-page">
                  <div class="hero-section">
                    <h1>Data Engineering & Architecture</h1>
                    <p class="lead">Cloud-Native Platforms and Enterprise Data Solutions</p>
                  </div>
                  
                  <div class="main-content">
                    <div class="row">
                      <div class="col-lg-8">
                        <h2>Enterprise Data Engineering</h2>
                        <p>We design and implement robust data architectures that enable organizations to harness the full power of their data. Our solutions span from real-time streaming to enterprise data lakes, built on modern cloud-native platforms.</p>
                        
                        <div class="capabilities-grid">
                          <div class="row">
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>Cloud-Native Platforms</h3>
                                <ul>
                                  <li>AWS (S3, Redshift, EMR, Glue)</li>
                                  <li>Azure (Data Factory, Synapse, Data Lake)</li>
                                  <li>Google Cloud Platform (BigQuery, Dataflow)</li>
                                  <li>Multi-cloud architecture design</li>
                                  <li>Cloud migration strategies</li>
                                </ul>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>Enterprise Data Architecture</h3>
                                <ul>
                                  <li>Data lake and data warehouse design</li>
                                  <li>Data mesh architecture implementation</li>
                                  <li>Master data management (MDM)</li>
                                  <li>Data governance frameworks</li>
                                  <li>Data quality and lineage tracking</li>
                                </ul>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>Real-Time Processing</h3>
                                <ul>
                                  <li>Apache Kafka and streaming pipelines</li>
                                  <li>Event-driven architecture</li>
                                  <li>Real-time analytics and dashboards</li>
                                  <li>Change data capture (CDC)</li>
                                  <li>Stream processing frameworks</li>
                                </ul>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="capability-card">
                                <h3>API Strategy</h3>
                                <ul>
                                  <li>RESTful API design and development</li>
                                  <li>GraphQL implementation</li>
                                  <li>API gateway and management</li>
                                  <li>Microservices architecture</li>
                                  <li>API security and authentication</li>
                                </ul>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="modern-data-stack">
                          <h3>Modern Data Stack Expertise</h3>
                          <div class="row">
                            <div class="col-md-6">
                              <h4>Databricks</h4>
                              <ul>
                                <li>Unified analytics platform setup</li>
                                <li>Delta Lake implementation</li>
                                <li>Collaborative notebook environments</li>
                                <li>MLflow integration</li>
                              </ul>
                            </div>
                            <div class="col-md-6">
                              <h4>Snowflake</h4>
                              <ul>
                                <li>Cloud data warehouse architecture</li>
                                <li>Data sharing and marketplace</li>
                                <li>Snowpipe and streaming ingestion</li>
                                <li>Performance optimization</li>
                              </ul>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <div class="col-lg-4">
                        <div class="technology-sidebar">
                          <h3>Technology Stack</h3>
                          <div class="tech-categories">
                            <h4>Data Platforms</h4>
                            <ul>
                              <li>Databricks</li>
                              <li>Snowflake</li>
                              <li>Apache Spark</li>
                              <li>Hadoop Ecosystem</li>
                              <li>Elasticsearch</li>
                            </ul>
                            
                            <h4>Streaming & ETL</h4>
                            <ul>
                              <li>Apache Kafka</li>
                              <li>Apache Airflow</li>
                              <li>dbt (data build tool)</li>
                              <li>Fivetran, Stitch</li>
                              <li>AWS Glue, Azure Data Factory</li>
                            </ul>
                            
                            <h4>Languages & Tools</h4>
                            <ul>
                              <li>Python, Scala, SQL</li>
                              <li>Docker & Kubernetes</li>
                              <li>Terraform, CloudFormation</li>
                              <li>Git, CI/CD pipelines</li>
                            </ul>
                            
                            <h4>Visualization</h4>
                            <ul>
                              <li>Tableau, Power BI</li>
                              <li>Looker, Grafana</li>
                              <li>Custom dashboard development</li>
                            </ul>
                          </div>
                          
                          <div class="cta-section mt-4">
                            <h4>Need Data Architecture?</h4>
                            <p>Let\'s design a scalable data platform for your organization.</p>
                            <a href="/contact-us" class="btn btn-primary btn-lg">Discuss Your Needs</a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the case studies page.
   */
  public function caseStudiesPage() {
    return [
      '#markup' => '
<article data-history-node-id="107" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Case Studies</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="case-studies-page">
  <h2>Case Studies</h2>
  <p class="lead">Real-world implementations delivering measurable business value across industries.</p>
  
  <div class="case-study-grid">
    <div class="case-study">
      <h3>Citigroup: Algorithmic Trading Platform</h3>
      <div class="case-study-meta">
        <span class="industry">FinTech</span>
        <span class="duration">18 months</span>
        <span class="team-size">12 professionals</span>
      </div>
      
      <div class="case-study-content">
        <h4>Challenge</h4>
        <p>Citigroup needed to modernize their algorithmic trading platform to handle increased trading volumes while improving risk management and regulatory compliance across 15 jurisdictions.</p>
        
        <h4>Solution</h4>
        <ul>
          <li>Implemented next-generation trading platform with ML-enhanced risk management</li>
          <li>Developed real-time fraud detection system processing 500K+ daily transactions</li>
          <li>Created predictive analytics models with 94% risk assessment accuracy</li>
          <li>Built automated compliance monitoring system</li>
        </ul>
        
        <h4>Results</h4>
        <div class="results-metrics">
          <div class="metric">
            <span class="value">$12M</span>
            <span class="label">Annual Cost Savings</span>
          </div>
          <div class="metric">
            <span class="value">87%</span>
            <span class="label">Reduction in Compliance Violations</span>
          </div>
          <div class="metric">
            <span class="value">23%</span>
            <span class="label">Reduction in Trading Losses</span>
          </div>
        </div>
      </div>
    </div>
    
    <div class="case-study">
      <h3>Signant Health: Clinical Trial Optimization</h3>
      <div class="case-study-meta">
        <span class="industry">Healthcare</span>
        <span class="duration">24 months</span>
        <span class="team-size">15 professionals</span>
      </div>
      
      <div class="case-study-content">
        <h4>Challenge</h4>
        <p>Signant Health required a modernized clinical trial management platform to serve 700+ active studies more efficiently while ensuring FDA/EMA compliance.</p>
        
        <h4>Solution</h4>
        <ul>
          <li>Developed AI-powered patient recruitment and retention optimization</li>
          <li>Implemented predictive analytics for clinical endpoint analysis</li>
          <li>Built comprehensive real-time trial monitoring platform</li>
          <li>Created automated regulatory reporting system</li>
        </ul>
        
        <h4>Results</h4>
        <div class="results-metrics">
          <div class="metric">
            <span class="value">28%</span>
            <span class="label">Reduction in Trial Duration</span>
          </div>
          <div class="metric">
            <span class="value">35%</span>
            <span class="label">Increase in Patient Retention</span>
          </div>
          <div class="metric">
            <span class="value">$2.1B</span>
            <span class="label">Drug Approvals Supported</span>
          </div>
        </div>
      </div>
    </div>
    
    <div class="case-study">
      <h3>NRG Energy: Smart Grid Implementation</h3>
      <div class="case-study-meta">
        <span class="industry">Energy</span>
        <span class="duration">16 months</span>
        <span class="team-size">10 professionals</span>
      </div>
      
      <div class="case-study-content">
        <h4>Challenge</h4>
        <p>NRG Energy needed to modernize their grid infrastructure to support renewable energy integration while improving reliability and reducing operational costs.</p>
        
        <h4>Solution</h4>
        <ul>
          <li>Designed IoT sensor network monitoring 50,000+ grid endpoints</li>
          <li>Developed AI-powered load forecasting system</li>
          <li>Implemented predictive maintenance algorithms</li>
          <li>Built renewable energy integration platform</li>
        </ul>
        
        <h4>Results</h4>
        <div class="results-metrics">
          <div class="metric">
            <span class="value">99.97%</span>
            <span class="label">Grid Reliability</span>
          </div>
          <div class="metric">
            <span class="value">$8.3M</span>
            <span class="label">Annual Cost Reduction</span>
          </div>
          <div class="metric">
            <span class="value">500MW+</span>
            <span class="label">Renewable Capacity Managed</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the contact page.
   */
  public function contactPage() {
    return [
      '#markup' => '
<article data-history-node-id="108" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Contact Us</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="contact-page">
  <div class="row">
    <div class="col-lg-8">
      <h2>Contact Us</h2>
      <p class="lead">Ready to transform your business with cutting-edge technology solutions? Let us discuss how we can help you achieve measurable results.</p>
      
      <div class="service-offerings">
        <h3>Service Offerings</h3>
        <div class="offerings-grid">
          <div class="offering-category">
            <h4>AI & Machine Learning</h4>
            <ul>
              <li>Predictive Analytics Implementation</li>
              <li>Deep Learning Model Development</li>
              <li>Natural Language Processing</li>
              <li>Computer Vision Solutions</li>
            </ul>
          </div>
          
          <div class="offering-category">
            <h4>Financial Technology</h4>
            <ul>
              <li>Algorithmic Trading Platforms</li>
              <li>Fraud Detection Systems</li>
              <li>Risk Management Solutions</li>
              <li>Regulatory Compliance Automation</li>
            </ul>
          </div>
          
          <div class="offering-category">
            <h4>Healthcare Technology</h4>
            <ul>
              <li>Clinical Trial Management</li>
              <li>Patient Data Analytics</li>
              <li>Medical Device Integration</li>
              <li>Regulatory Compliance Systems</li>
            </ul>
          </div>
          
          <div class="offering-category">
            <h4>Energy Solutions</h4>
            <ul>
              <li>Smart Grid Implementation</li>
              <li>Renewable Energy Integration</li>
              <li>Predictive Maintenance</li>
              <li>Supply Chain Optimization</li>
            </ul>
          </div>
        </div>
      </div>
      
      <div class="engagement-models">
        <h3>Engagement Models</h3>
        <div class="models-grid">
          <div class="model-card">
            <h4>Strategic Consulting</h4>
            <p>High-level technology strategy and roadmap development for digital transformation initiatives.</p>
          </div>
          <div class="model-card">
            <h4>Implementation Services</h4>
            <p>End-to-end solution development and deployment with ongoing support and optimization.</p>
          </div>
          <div class="model-card">
            <h4>Staff Augmentation</h4>
            <p>Embedded specialists to enhance your existing team capabilities with domain expertise.</p>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-lg-4">
      <div class="contact-info">
        <h3>Get In Touch</h3>
        <div class="contact-item">
          <h4>Email</h4>
          <p><a href="mailto:contact@stlouisintegration.com">contact@stlouisintegration.com</a></p>
        </div>
        
        <div class="contact-item">
          <h4>Phone</h4>
          <p><a href="tel:+1-314-369-0811">(314) 369-0811</a></p>
        </div>
        
        <div class="contact-item">
          <h4>Location</h4>
          <p>St. Louis, Missouri<br>Serving clients globally</p>
        </div>
        
        <div class="contact-item">
          <h4>Response Time</h4>
          <p>We typically respond to inquiries within 24 hours</p>
        </div>
        
        <div class="capabilities-summary">
          <h4>Quick Facts</h4>
          <ul>
            <li><strong>15+</strong> years experience</li>
            <li><strong>Fortune 500</strong> client portfolio</li>
            <li><strong>$50M+</strong> in verified savings delivered</li>
            <li><strong>700+</strong> clinical trials supported</li>
            <li><strong>99.99%</strong> uptime SLA achievement</li>
            <li><strong>Security clearance</strong> available</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the leadership page.
   */
  public function leadershipPage() {
    return [
      '#markup' => '
<article data-history-node-id="26" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Leadership</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="leadership-page">
  <h2>Leadership Team</h2>
  <p class="lead">Experienced technology leaders driving innovation across Financial Services, Healthcare, and Energy sectors.</p>
  
  <div class="leader-profile">
    <div class="row">
      <div class="col-lg-4">
        <div class="leader-image">
          <div class="placeholder-image">
            &nbsp;
          </div>
        </div>
      </div>
      <div class="col-lg-8">
        <h3>Keith Aumiller</h3>
        <h4>Founder & Principal Consultant</h4>
        
        <p>Keith brings over 25 years of enterprise technology consulting experience, specializing in AI-powered solutions and digital transformation. His expertise spans Financial Services, Healthcare, and Energy sectors, with a proven track record of delivering operational efficiency improvements of 30%+ and cost savings exceeding $50 million.</p>
        
        <div class="credentials-section">
          <h5>Education</h5>
          <ul>
            <li><strong>MBA</strong> - Washington University in St. Louis (AI Focus)</li>
            <li><p><strong>Bachelor of Science in Psychology</strong> - Truman State University</p><p>&nbsp;</p></li>
          </ul>
          
          <h5>Expertise</h5>
          <div class="certifications-grid">
            <div class="cert-item">
              <strong>AWS  Solutions Architect</strong> - Professional
            </div>
            <div class="cert-item">
              <strong>Google Cloud Professional</strong> - Machine Learning Engineer
            </div>
            <div class="cert-item">
              <strong>Microsoft Azure</strong> - AI Engineer 
            </div>
            <div class="cert-item">
              <strong>PMI Project Management</strong> - Professional (PMP)
            </div>
          </div>
          
          <h5>Key Achievements</h5>
          <div class="achievements-grid">
            <div class="achievement">
              <span class="number">700+</span>
              <span class="desc">Clinical Trials Supported</span>
            </div>
            <div class="achievement">
              <span class="number">$50M+</span>
              <span class="desc">Fraud Prevention Savings</span>
            </div>
            <div class="achievement">
              <span class="number">25+</span>
              <span class="desc">Enterprise AI/ML Deployments</span>
            </div>
            <div class="achievement">
              <span class="number">100%</span>
              <span class="desc">Regulatory Audit Success Rate</span>
            </div>
          </div>
          
          <h5>Security & Data Experience</h5>
          <ul>
            <li>US Government Trust</li>
            <li>GDPR Compliance Specialist</li>
            <li>HIPAA Expertise</li>
            <li>CDISC Expertise</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the privacy policy page.
   */
  public function privacyPolicyPage() {
    return [
      '#markup' => '
<article data-history-node-id="200" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Privacy Policy</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="privacy-policy-page">
  <h2>Privacy Policy</h2>
  <p><strong>Last updated:</strong> September 29, 2025</p>
  
  <h3>Information We Collect</h3>
  <p>St. Louis Integration LLC is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and safeguard your information when you visit our website or use our services.</p>
  
  <h3>Types of Information We Collect</h3>
  <ul>
    <li><strong>Personal Information:</strong> Name, email address, phone number, and other contact details you provide</li>
    <li><strong>Usage Data:</strong> Information about how you use our website and services</li>
    <li><strong>Technical Data:</strong> IP address, browser type, device information, and cookies</li>
  </ul>
  
  <h3>How We Use Information</h3>
  <p>We use the information we collect to:</p>
  <ul>
    <li>Provide, maintain, and improve our services</li>
    <li>Communicate with you about our services</li>
    <li>Respond to your inquiries and support requests</li>
    <li>Comply with legal obligations</li>
  </ul>
  
  <h3>Information Sharing</h3>
  <p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy or as required by law.</p>
  
  <h3>Data Security</h3>
  <p>We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>
  
  <h3>Your Rights</h3>
  <p>You have the right to access, update, or delete your personal information. Contact us to exercise these rights.</p>
  
  <h3>Contact Information</h3>
  <p>If you have questions about this Privacy Policy, please contact us through our website contact form.</p>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the terms of service page.
   */
  public function termsOfServicePage() {
    return [
      '#markup' => '
<article data-history-node-id="201" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Terms of Service</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="terms-of-service-page">
  <h2>Terms of Service</h2>
  <p><strong>Last updated:</strong> September 29, 2025</p>
  
  <h3>Agreement to Terms</h3>
  <p>By accessing and using the services provided by St. Louis Integration LLC, you agree to be bound by these Terms of Service.</p>
  
  <h3>Description of Services</h3>
  <p>St. Louis Integration LLC provides AI solutions, data engineering, cloud migration, and system integration services to enterprise clients.</p>
  
  <h3>User Responsibilities</h3>
  <ul>
    <li>Use our services in compliance with applicable laws and regulations</li>
    <li>Provide accurate and complete information when requested</li>
    <li>Maintain the confidentiality of any login credentials</li>
    <li>Not use our services for any unlawful or prohibited purposes</li>
  </ul>
  
  <h3>Intellectual Property</h3>
  <p>All content, trademarks, and intellectual property on this website are owned by St. Louis Integration LLC and are protected by applicable laws.</p>
  
  <h3>Limitation of Liability</h3>
  <p>St. Louis Integration LLC shall not be liable for any indirect, incidental, special, or consequential damages arising from the use of our services.</p>
  
  <h3>Modification of Terms</h3>
  <p>We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting.</p>
  
  <h3>Contact Information</h3>
  <p>For questions about these Terms of Service, please contact us through our website.</p>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the sitemap page.
   */
  public function sitemapPage() {
    return [
      '#markup' => '
<article data-history-node-id="202" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Sitemap</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="sitemap-page">
  <h2>Site Map</h2>
  <p>Navigate our website easily with this comprehensive site map.</p>
  
  <div class="row">
    <div class="col-md-6">
      <h3>Main Pages</h3>
      <ul>
        <li><a href="/">Home</a></li>
        <li><a href="/services">Services</a></li>
        <li><a href="/contact-us">Contact</a></li>
        <li><a href="/leadership">Leadership</a></li>
      </ul>
      
      <h3>Services</h3>
      <ul>
        <li><a href="/services">AI Solutions</a></li>
        <li><a href="/services">Data Engineering</a></li>
        <li><a href="/services">Cloud Migration</a></li>
        <li><a href="/services">System Integration</a></li>
        <li><a href="/services">Digital Transformation</a></li>
      </ul>
    </div>
    
    <div class="col-md-6">
      <h3>Industries</h3>
      <ul>
        <li><a href="/industries/fintech">Financial Services</a></li>
        <li><a href="/industries/healthcare">Healthcare</a></li>
        <li><a href="/industries/energy">Energy & Utilities</a></li>
      </ul>
      
      <h3>Resources</h3>
      <ul>
        <li><a href="/case-studies">Case Studies</a></li>
        <li><a href="https://thetruthperspective.org">The Truth Perspective</a></li>
      </ul>
      
      <h3>Legal</h3>
      <ul>
        <li><a href="/privacy-policy">Privacy Policy</a></li>
        <li><a href="/terms-of-service">Terms of Service</a></li>
        <li><a href="/accessibility">Accessibility</a></li>
      </ul>
    </div>
  </div>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

  /**
   * Display the accessibility page.
   */
  public function accessibilityPage() {
    return [
      '#markup' => '
<article data-history-node-id="203" class="node node--type-page node--view-mode-full">
  <div class="node__content page-content default-page">
    <h1 class="page-title"><span>Accessibility</span></h1>
    <div class="page-intro">
      Explore our professional services and innovative solutions designed to drive your business forward.
    </div>
    <div class="content-cards">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div data-component-id="radix:field" class="field field--name-body field--type-text-with-summary field--label-hidden field--item">
<div class="accessibility-page">
  <h2>Accessibility Statement</h2>
  <p><strong>Last updated:</strong> September 29, 2025</p>
  
  <h3>Our Commitment</h3>
  <p>St. Louis Integration LLC is committed to ensuring digital accessibility for people with disabilities. We are continually improving the user experience for everyone and applying the relevant accessibility standards.</p>
  
  <h3>Standards</h3>
  <p>We strive to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 Level AA standards. These guidelines help make web content more accessible to people with disabilities.</p>
  
  <h3>Accessibility Features</h3>
  <ul>
    <li>Semantic HTML markup for screen readers</li>
    <li>Keyboard navigation support</li>
    <li>High contrast color schemes</li>
    <li>Descriptive alt text for images</li>
    <li>Proper heading structure</li>
    <li>Focus indicators for interactive elements</li>
  </ul>
  
  <h3>Known Issues</h3>
  <p>We are continuously working to improve accessibility. If you encounter any accessibility barriers, please let us know.</p>
  
  <h3>Third-Party Content</h3>
  <p>Some content on our website may be provided by third parties. We work with these providers to ensure accessibility compliance where possible.</p>
  
  <h3>Feedback</h3>
  <p>We welcome your feedback on the accessibility of our website. Please contact us if you encounter accessibility barriers or have suggestions for improvement.</p>
  
  <h3>Contact Information</h3>
  <p>For accessibility-related questions or concerns, please contact us through our website contact form.</p>
</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>',
      '#allowed_tags' => ['article', 'div', 'h1', 'h2', 'h3', 'h4', 'p', 'ul', 'li', 'a', 'strong', 'em', 'span'],
    ];
  }

}