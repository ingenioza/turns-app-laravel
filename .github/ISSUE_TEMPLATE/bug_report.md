name: Bug report
description: Report a problem to help us improve
title: "bug: "
labels: [bug]
assignees: []
body:
  - type: textarea
    attributes:
      label: Describe the bug
      description: A clear and concise description of what the bug is.
    validations:
      required: true
  - type: textarea
    attributes:
      label: Steps to reproduce
      description: Provide steps so we can reproduce the issue.
    validations:
      required: true
  - type: textarea
    attributes:
      label: Expected behavior
    validations:
      required: true
  - type: textarea
    attributes:
      label: Screenshots/Logs
    validations:
      required: false
