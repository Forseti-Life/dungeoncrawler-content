#!/usr/bin/env python3
"""
H3 Data Validation Test Runner

Comprehensive test runner that orchestrates all data validation tools to provide
complete H3 pipeline validation and integrity reporting.

This runner executes:
1. Data integrity reporting across all pipeline layers
2. Exclusion analysis for record passthrough tracking
3. H3 metrics calculation and validation
4. Comprehensive pipeline validation reporting

Usage:
    python run_validation_tests.py --full-validation
    python run_validation_tests.py --quick-check
    python run_validation_tests.py --layer transform
    python run_validation_tests.py --report-only
"""

import sys
import os
import json
import logging
import argparse
from datetime import datetime
from typing import Dict, List, Optional
import subprocess

# Add parent directories to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

# Import validation tools
from data_integrity_reporter import H3DataIntegrityReporter
from exclusion_analyzer import H3ExclusionAnalyzer
from h3_metrics_calculator import H3MetricsCalculator

class ValidationTestRunner:
    """
    Comprehensive test runner for H3 pipeline validation.
    Orchestrates all validation tools to provide complete pipeline analysis.
    """
    
    def __init__(self):
        """Initialize the validation test runner."""
        # Setup logging
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s'
        )
        self.logger = logging.getLogger(__name__)
        
        # Initialize validation tools
        self.integrity_reporter = H3DataIntegrityReporter()
        self.exclusion_analyzer = H3ExclusionAnalyzer()
        self.metrics_calculator = H3MetricsCalculator()
        
        # Test results storage
        self.test_results = {
            'test_run_timestamp': datetime.now().isoformat(),
            'validation_results': {},
            'test_summary': {},
            'recommendations': []
        }
    
    def run_data_integrity_tests(self) -> Dict:
        """Run comprehensive data integrity tests."""
        self.logger.info("🔍 Running data integrity validation tests...")
        
        try:
            # Generate integrity report
            integrity_report = self.integrity_reporter.generate_integrity_report()
            
            # Assess integrity results
            integrity_assessment = self._assess_integrity_results(integrity_report)
            
            return {
                'test_name': 'Data Integrity Validation',
                'status': 'completed',
                'results': integrity_report,
                'assessment': integrity_assessment,
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Data integrity tests failed: {e}")
            return {
                'test_name': 'Data Integrity Validation',
                'status': 'failed',
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }
    
    def run_exclusion_analysis_tests(self) -> Dict:
        """Run exclusion analysis tests."""
        self.logger.info("🔍 Running exclusion analysis tests...")
        
        try:
            # Generate exclusion analysis
            exclusion_report = self.exclusion_analyzer.generate_exclusion_report()
            
            # Assess exclusion results
            exclusion_assessment = self._assess_exclusion_results(exclusion_report)
            
            return {
                'test_name': 'Exclusion Analysis',
                'status': 'completed',
                'results': exclusion_report,
                'assessment': exclusion_assessment,
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Exclusion analysis tests failed: {e}")
            return {
                'test_name': 'Exclusion Analysis',
                'status': 'failed',
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }
    
    def run_h3_metrics_tests(self) -> Dict:
        """Run H3 metrics validation tests."""
        self.logger.info("🔍 Running H3 metrics validation tests...")
        
        try:
            # Generate H3 metrics report
            metrics_report = self.metrics_calculator.generate_h3_metrics_report()
            
            # Assess metrics results
            metrics_assessment = self._assess_metrics_results(metrics_report)
            
            return {
                'test_name': 'H3 Metrics Validation',
                'status': 'completed',
                'results': metrics_report,
                'assessment': metrics_assessment,
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"H3 metrics tests failed: {e}")
            return {
                'test_name': 'H3 Metrics Validation',
                'status': 'failed',
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }
    
    def run_quick_validation_check(self) -> Dict:
        """Run quick validation check with essential metrics."""
        self.logger.info("⚡ Running quick validation check...")
        
        try:
            # Quick integrity check
            connection = self.integrity_reporter.connect_to_mysql()
            quick_stats = self._get_quick_pipeline_stats(connection)
            connection.close()
            
            # Quick assessment
            quick_assessment = self._assess_quick_stats(quick_stats)
            
            return {
                'test_name': 'Quick Validation Check',
                'status': 'completed',
                'results': quick_stats,
                'assessment': quick_assessment,
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Quick validation check failed: {e}")
            return {
                'test_name': 'Quick Validation Check',
                'status': 'failed',
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }
    
    def _get_quick_pipeline_stats(self, connection) -> Dict:
        """Get quick pipeline statistics."""
        cursor = connection.cursor(dictionary=True)
        
        # Raw layer stats
        cursor.execute("SELECT COUNT(*) as raw_count FROM amisafe_raw_incidents")
        raw_stats = cursor.fetchone()
        
        # Transform layer stats
        cursor.execute("SELECT COUNT(*) as transform_count, COUNT(CASE WHEN is_valid = TRUE THEN 1 END) as valid_count FROM amisafe_clean_incidents")
        transform_stats = cursor.fetchone()
        
        # H3 coverage stats
        cursor.execute("SELECT COUNT(DISTINCT h3_res_9) as unique_h3_res9 FROM amisafe_clean_incidents WHERE is_valid = TRUE")
        h3_stats = cursor.fetchone()
        
        cursor.close()
        
        return {
            'raw_layer': {
                'total_records': raw_stats['raw_count']
            },
            'transform_layer': {
                'total_records': transform_stats['transform_count'],
                'valid_records': transform_stats['valid_count'],
                'validation_rate_pct': round((transform_stats['valid_count'] / transform_stats['transform_count'] * 100), 2) if transform_stats['transform_count'] > 0 else 0
            },
            'h3_coverage': {
                'unique_res9_hexagons': h3_stats['unique_h3_res9']
            }
        }
    
    def _assess_integrity_results(self, integrity_report: Dict) -> Dict:
        """Assess data integrity results."""
        assessment = {
            'overall_status': 'unknown',
            'issues_found': [],
            'strengths': [],
            'recommendations': []
        }
        
        try:
            if 'pipeline_analysis' in integrity_report:
                pipeline = integrity_report['pipeline_analysis']
                
                # Assess transform layer
                if 'transform_layer' in pipeline:
                    transform = pipeline['transform_layer']
                    if 'data_quality_metrics' in transform:
                        quality = transform['data_quality_metrics']
                        validation_rate = float(quality.get('validation_success_rate_pct', 0))
                        
                        if validation_rate >= 90:
                            assessment['strengths'].append(f"Excellent validation rate: {validation_rate}%")
                        elif validation_rate >= 70:
                            assessment['strengths'].append(f"Good validation rate: {validation_rate}%")
                        else:
                            assessment['issues_found'].append(f"Low validation rate: {validation_rate}%")
                
                # Assess data flow
                if 'data_flow_analysis' in integrity_report:
                    flow = integrity_report['data_flow_analysis']
                    if 'throughput_efficiency_pct' in flow:
                        efficiency = float(flow['throughput_efficiency_pct'])
                        if efficiency >= 80:
                            assessment['strengths'].append(f"High throughput efficiency: {efficiency}%")
                        else:
                            assessment['issues_found'].append(f"Low throughput efficiency: {efficiency}%")
            
            # Determine overall status
            if len(assessment['issues_found']) == 0:
                assessment['overall_status'] = 'excellent'
            elif len(assessment['issues_found']) <= 2:
                assessment['overall_status'] = 'good'
            else:
                assessment['overall_status'] = 'needs_attention'
                
        except Exception as e:
            assessment['issues_found'].append(f"Assessment error: {str(e)}")
            assessment['overall_status'] = 'error'
        
        return assessment
    
    def _assess_exclusion_results(self, exclusion_report: Dict) -> Dict:
        """Assess exclusion analysis results."""
        assessment = {
            'overall_status': 'unknown',
            'exclusion_patterns': [],
            'recovery_opportunities': [],
            'recommendations': []
        }
        
        try:
            if 'exclusion_analysis' in exclusion_report:
                analysis = exclusion_report['exclusion_analysis']
                
                # Assess raw layer exclusions
                if 'raw_layer_exclusions' in analysis:
                    raw_exclusions = analysis['raw_layer_exclusions']
                    for category, data in raw_exclusions.items():
                        if isinstance(data, dict) and 'exclusion_count' in data:
                            count = data['exclusion_count']
                            if count > 1000:
                                assessment['exclusion_patterns'].append(f"High {category} exclusions: {count:,}")
                
                # Check recovery recommendations
                if 'recommendations' in exclusion_report:
                    assessment['recovery_opportunities'] = exclusion_report['recommendations'][:3]  # Top 3
            
            # Determine overall status based on exclusion patterns
            if len(assessment['exclusion_patterns']) == 0:
                assessment['overall_status'] = 'excellent'
            elif len(assessment['exclusion_patterns']) <= 2:
                assessment['overall_status'] = 'good'
            else:
                assessment['overall_status'] = 'needs_attention'
                
        except Exception as e:
            assessment['exclusion_patterns'].append(f"Assessment error: {str(e)}")
            assessment['overall_status'] = 'error'
        
        return assessment
    
    def _assess_metrics_results(self, metrics_report: Dict) -> Dict:
        """Assess H3 metrics results."""
        assessment = {
            'overall_status': 'unknown',
            'h3_performance': [],
            'coverage_analysis': [],
            'recommendations': []
        }
        
        try:
            if 'h3_metrics_analysis' in metrics_report:
                analysis = metrics_report['h3_metrics_analysis']
                
                # Assess indexing validation
                if 'indexing_validation' in analysis:
                    validation = analysis['indexing_validation']
                    if 'validation_summary' in validation:
                        accuracy = float(validation['validation_summary'].get('overall_accuracy_pct', 0))
                        if accuracy >= 95:
                            assessment['h3_performance'].append(f"Excellent H3 indexing accuracy: {accuracy}%")
                        elif accuracy >= 90:
                            assessment['h3_performance'].append(f"Good H3 indexing accuracy: {accuracy}%")
                        else:
                            assessment['h3_performance'].append(f"H3 indexing needs attention: {accuracy}%")
                
                # Assess coverage comparison
                if 'coverage_comparison' in analysis:
                    comparison = analysis['coverage_comparison']
                    if 'efficiency_metrics' in comparison:
                        efficiency = float(comparison['efficiency_metrics'].get('average_coverage_efficiency_pct', 0))
                        if efficiency >= 60:
                            assessment['coverage_analysis'].append(f"Good H3 coverage efficiency: {efficiency}%")
                        else:
                            assessment['coverage_analysis'].append(f"H3 coverage needs improvement: {efficiency}%")
                
                # Get recommendations
                if 'recommendations' in metrics_report:
                    assessment['recommendations'] = metrics_report['recommendations'][:3]  # Top 3
            
            # Determine overall status
            issues = [item for item in assessment['h3_performance'] + assessment['coverage_analysis'] if 'needs' in item or 'attention' in item]
            if len(issues) == 0:
                assessment['overall_status'] = 'excellent'
            elif len(issues) <= 1:
                assessment['overall_status'] = 'good'
            else:
                assessment['overall_status'] = 'needs_attention'
                
        except Exception as e:
            assessment['h3_performance'].append(f"Assessment error: {str(e)}")
            assessment['overall_status'] = 'error'
        
        return assessment
    
    def _assess_quick_stats(self, quick_stats: Dict) -> Dict:
        """Assess quick validation statistics."""
        assessment = {
            'overall_status': 'unknown',
            'quick_insights': [],
            'data_pipeline_health': 'unknown'
        }
        
        try:
            # Check validation rate
            if 'transform_layer' in quick_stats:
                validation_rate = float(quick_stats['transform_layer'].get('validation_rate_pct', 0))
                if validation_rate >= 90:
                    assessment['quick_insights'].append(f"✅ High validation rate: {validation_rate}%")
                    assessment['data_pipeline_health'] = 'healthy'
                elif validation_rate >= 70:
                    assessment['quick_insights'].append(f"⚠️ Moderate validation rate: {validation_rate}%")
                    assessment['data_pipeline_health'] = 'moderate'
                else:
                    assessment['quick_insights'].append(f"❌ Low validation rate: {validation_rate}%")
                    assessment['data_pipeline_health'] = 'needs_attention'
            
            # Check H3 coverage
            if 'h3_coverage' in quick_stats:
                h3_hexagons = quick_stats['h3_coverage'].get('unique_res9_hexagons', 0)
                if h3_hexagons > 1000:
                    assessment['quick_insights'].append(f"✅ Good H3 coverage: {h3_hexagons:,} hexagons")
                elif h3_hexagons > 500:
                    assessment['quick_insights'].append(f"⚠️ Moderate H3 coverage: {h3_hexagons:,} hexagons")
                else:
                    assessment['quick_insights'].append(f"❌ Limited H3 coverage: {h3_hexagons:,} hexagons")
            
            # Overall status
            if assessment['data_pipeline_health'] == 'healthy':
                assessment['overall_status'] = 'excellent'
            elif assessment['data_pipeline_health'] == 'moderate':
                assessment['overall_status'] = 'good'
            else:
                assessment['overall_status'] = 'needs_attention'
                
        except Exception as e:
            assessment['quick_insights'].append(f"Assessment error: {str(e)}")
            assessment['overall_status'] = 'error'
        
        return assessment
    
    def run_full_validation_suite(self) -> Dict:
        """Run complete validation test suite."""
        self.logger.info("🚀 Starting full H3 pipeline validation suite...")
        
        # Run all validation tests
        self.test_results['validation_results']['data_integrity'] = self.run_data_integrity_tests()
        self.test_results['validation_results']['exclusion_analysis'] = self.run_exclusion_analysis_tests()
        self.test_results['validation_results']['h3_metrics'] = self.run_h3_metrics_tests()
        
        # Generate overall summary
        self.test_results['test_summary'] = self._generate_test_summary()
        
        # Generate consolidated recommendations
        self.test_results['recommendations'] = self._generate_consolidated_recommendations()
        
        self.logger.info("✅ Full validation suite completed")
        return self.test_results
    
    def _generate_test_summary(self) -> Dict:
        """Generate overall test summary."""
        summary = {
            'total_tests': len(self.test_results['validation_results']),
            'completed_tests': 0,
            'failed_tests': 0,
            'overall_status': 'unknown',
            'key_findings': []
        }
        
        # Count test results
        for test_name, test_result in self.test_results['validation_results'].items():
            if test_result.get('status') == 'completed':
                summary['completed_tests'] += 1
            elif test_result.get('status') == 'failed':
                summary['failed_tests'] += 1
            
            # Extract key findings from assessments
            if 'assessment' in test_result:
                assessment = test_result['assessment']
                if 'strengths' in assessment:
                    summary['key_findings'].extend(assessment['strengths'][:2])  # Top 2 strengths
                if 'issues_found' in assessment:
                    summary['key_findings'].extend(assessment['issues_found'][:2])  # Top 2 issues
        
        # Determine overall status
        if summary['failed_tests'] == 0 and summary['completed_tests'] == summary['total_tests']:
            summary['overall_status'] = 'all_tests_passed'
        elif summary['failed_tests'] > 0:
            summary['overall_status'] = 'some_tests_failed'
        else:
            summary['overall_status'] = 'incomplete'
        
        return summary
    
    def _generate_consolidated_recommendations(self) -> List[str]:
        """Generate consolidated recommendations from all tests."""
        all_recommendations = []
        
        # Collect recommendations from all test results
        for test_name, test_result in self.test_results['validation_results'].items():
            if 'results' in test_result and 'recommendations' in test_result['results']:
                all_recommendations.extend(test_result['results']['recommendations'])
            if 'assessment' in test_result and 'recommendations' in test_result['assessment']:
                all_recommendations.extend(test_result['assessment']['recommendations'])
        
        # Deduplicate and prioritize recommendations
        unique_recommendations = []
        seen = set()
        for rec in all_recommendations:
            if rec not in seen:
                unique_recommendations.append(rec)
                seen.add(rec)
        
        return unique_recommendations[:10]  # Top 10 recommendations

def main():
    """Main execution function."""
    parser = argparse.ArgumentParser(description='H3 Data Validation Test Runner')
    parser.add_argument('--full-validation', action='store_true', help='Run complete validation suite')
    parser.add_argument('--quick-check', action='store_true', help='Run quick validation check')
    parser.add_argument('--layer', choices=['raw', 'transform', 'final'], help='Validate specific layer')
    parser.add_argument('--report-only', action='store_true', help='Generate reports without assessments')
    parser.add_argument('--output', default='console', choices=['console', 'json'], help='Output format')
    
    args = parser.parse_args()
    
    # Initialize test runner
    runner = ValidationTestRunner()
    
    print("="*100)
    print("H3 DATA VALIDATION TEST RUNNER")
    print("="*100)
    print(f"Test Run Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*100)
    
    try:
        if args.full_validation:
            # Run full validation suite
            results = runner.run_full_validation_suite()
            
            if args.output == 'json':
                print(json.dumps(results, indent=2))
            else:
                print_validation_summary(results)
        
        elif args.quick_check:
            # Run quick validation check
            results = runner.run_quick_validation_check()
            
            if args.output == 'json':
                print(json.dumps(results, indent=2))
            else:
                print_quick_check_summary(results)
        
        else:
            # Default to quick check
            results = runner.run_quick_validation_check()
            print_quick_check_summary(results)
        
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)

def print_validation_summary(results):
    """Print formatted validation summary."""
    print("\n📊 VALIDATION TEST SUMMARY")
    print("-" * 60)
    
    if 'test_summary' in results:
        summary = results['test_summary']
        print(f"Total Tests: {summary['total_tests']}")
        print(f"Completed: {summary['completed_tests']}")
        print(f"Failed: {summary['failed_tests']}")
        print(f"Overall Status: {summary['overall_status'].replace('_', ' ').title()}")
    
    print("\n🔍 TEST RESULTS")
    print("-" * 60)
    for test_name, test_result in results.get('validation_results', {}).items():
        status_icon = "✅" if test_result.get('status') == 'completed' else "❌"
        print(f"{status_icon} {test_result.get('test_name', test_name)}: {test_result.get('status', 'unknown').title()}")
        
        if 'assessment' in test_result:
            assessment = test_result['assessment']
            if 'overall_status' in assessment:
                print(f"   Assessment: {assessment['overall_status'].replace('_', ' ').title()}")
    
    print("\n💡 KEY RECOMMENDATIONS")
    print("-" * 60)
    for i, rec in enumerate(results.get('recommendations', [])[:5], 1):
        print(f"{i}. {rec}")
    
    print("\n" + "="*100)

def print_quick_check_summary(results):
    """Print formatted quick check summary."""
    print("\n⚡ QUICK VALIDATION CHECK")
    print("-" * 50)
    
    if 'assessment' in results:
        assessment = results['assessment']
        status_icon = "✅" if assessment.get('overall_status') == 'excellent' else "⚠️" if assessment.get('overall_status') == 'good' else "❌"
        print(f"Overall Status: {status_icon} {assessment.get('overall_status', 'unknown').replace('_', ' ').title()}")
        
        print("\nQuick Insights:")
        for insight in assessment.get('quick_insights', []):
            print(f"  {insight}")
    
    if 'results' in results:
        data = results['results']
        print("\nData Pipeline Summary:")
        if 'raw_layer' in data:
            print(f"  Raw Records: {data['raw_layer']['total_records']:,}")
        if 'transform_layer' in data:
            transform = data['transform_layer']
            print(f"  Valid Records: {transform['valid_records']:,} ({transform['validation_rate_pct']}%)")
        if 'h3_coverage' in data:
            print(f"  H3 Hexagons: {data['h3_coverage']['unique_res9_hexagons']:,}")
    
    print("\n" + "="*100)

if __name__ == "__main__":
    main()