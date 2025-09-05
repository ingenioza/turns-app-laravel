name: Feature request
description: Suggest an idea for this project
title: "feat: "
labels: [enhancement]
assignees: []
body:
  - type: textarea
    attributes:
      label: Summary
      description: What problem does this feature solve?
    validations:
      required: true
  - type: textarea
    attributes:
      label: Acceptance criteria
      description: Bullet list of criteria for completion.
    validations:
      required: true
  - type: textarea
    attributes:
      label: Alternatives/risks
    validations:
      required: false
