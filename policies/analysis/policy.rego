package hq.analysis

deny[msg] {
  not has_field(input, "analysis_id")
  msg := "missing required field: analysis_id"
}

deny[msg] {
  not has_nonempty_string(input, "analysis_id")
  msg := "analysis_id must be a non-empty string"
}

deny[msg] {
  not has_nonempty_string(input, "objective")
  msg := "missing required non-empty field: objective"
}

deny[msg] {
  not has_nonempty_string(input, "decision_owner")
  msg := "missing required non-empty field: decision_owner"
}

deny[msg] {
  not has_object(input, "scope")
  msg := "missing required object: scope"
}

deny[msg] {
  has_object(input, "scope")
  not has_nonempty_array(input.scope, "in_scope")
  msg := "scope.in_scope must be a non-empty array"
}

deny[msg] {
  has_object(input, "scope")
  not has_nonempty_array(input.scope, "out_of_scope")
  msg := "scope.out_of_scope must be a non-empty array"
}

deny[msg] {
  not has_nonempty_array(input, "assumptions")
  msg := "assumptions must be a non-empty array"
}

deny[msg] {
  not has_nonempty_array(input, "evidence")
  msg := "evidence must be a non-empty array"
}

deny[msg] {
  not has_nonempty_array(input, "counter_evidence")
  msg := "counter_evidence must be a non-empty array"
}

deny[msg] {
  not has_nonempty_array(input, "options")
  msg := "options must be a non-empty array"
}

deny[msg] {
  has_nonempty_array(input, "options")
  count(input.options) < 2
  msg := "options must contain at least two options"
}

deny[msg] {
  not has_object(input, "recommendation")
  msg := "missing required object: recommendation"
}

deny[msg] {
  has_object(input, "recommendation")
  not has_nonempty_string(input.recommendation, "option_id")
  msg := "recommendation.option_id must be a non-empty string"
}

deny[msg] {
  has_object(input, "recommendation")
  not has_nonempty_string(input.recommendation, "rationale")
  msg := "recommendation.rationale must be a non-empty string"
}

deny[msg] {
  has_object(input, "recommendation")
  not has_nonempty_array(input.recommendation, "tradeoffs")
  msg := "recommendation.tradeoffs must be a non-empty array"
}

deny[msg] {
  has_object(input, "recommendation")
  not has_nonempty_string(input.recommendation, "decision_needed")
  msg := "recommendation.decision_needed must be a non-empty string"
}

deny[msg] {
  not has_field(input, "confidence")
  msg := "missing required field: confidence"
}

deny[msg] {
  has_field(input, "confidence")
  not is_number(input.confidence)
  msg := "confidence must be numeric between 0 and 1"
}

deny[msg] {
  is_number(input.confidence)
  input.confidence < 0
  msg := "confidence must be >= 0"
}

deny[msg] {
  is_number(input.confidence)
  input.confidence > 1
  msg := "confidence must be <= 1"
}

deny[msg] {
  not has_object(input, "verification")
  msg := "missing required object: verification"
}

deny[msg] {
  has_object(input, "verification")
  not has_nonempty_array(input.verification, "acceptance_criteria")
  msg := "verification.acceptance_criteria must be a non-empty array"
}

deny[msg] {
  has_object(input, "verification")
  not has_nonempty_array(input.verification, "checks")
  msg := "verification.checks must be a non-empty array"
}

deny[msg] {
  some i
  evidence := input.evidence[i]
  not has_nonempty_string(evidence, "source")
  msg := sprintf("evidence[%d].source must be a non-empty string", [i])
}

deny[msg] {
  some i
  evidence := input.evidence[i]
  not has_nonempty_string(evidence, "summary")
  msg := sprintf("evidence[%d].summary must be a non-empty string", [i])
}

deny[msg] {
  some i
  evidence := input.evidence[i]
  not has_field(evidence, "reliability")
  msg := sprintf("evidence[%d].reliability is required (1-5)", [i])
}

deny[msg] {
  some i
  evidence := input.evidence[i]
  has_field(evidence, "reliability")
  not is_number(evidence.reliability)
  msg := sprintf("evidence[%d].reliability must be numeric (1-5)", [i])
}

deny[msg] {
  some i
  evidence := input.evidence[i]
  is_number(evidence.reliability)
  evidence.reliability < 1
  msg := sprintf("evidence[%d].reliability must be >= 1", [i])
}

deny[msg] {
  some i
  evidence := input.evidence[i]
  is_number(evidence.reliability)
  evidence.reliability > 5
  msg := sprintf("evidence[%d].reliability must be <= 5", [i])
}

deny[msg] {
  has_object(input, "recommendation")
  has_nonempty_string(input.recommendation, "option_id")
  not option_id_exists(input.options, input.recommendation.option_id)
  msg := "recommendation.option_id must match one of options[].id"
}

option_id_exists(options, option_id) {
  some i
  option := options[i]
  has_nonempty_string(option, "id")
  option.id == option_id
}

has_field(obj, key) {
  obj[key]
}

has_object(obj, key) {
  value := obj[key]
  is_object(value)
}

has_nonempty_string(obj, key) {
  value := obj[key]
  is_string(value)
  value != ""
}

has_nonempty_array(obj, key) {
  value := obj[key]
  is_array(value)
  count(value) > 0
}
