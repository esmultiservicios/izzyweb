# Core Branch Strategy

The reusable core must stay neutral, stable and free of project-specific branding or data.

```text
cms-core-main
├── project-a
├── project-b
├── project-c
└── phase2-multitenant
```

## Rules

1. Create each branded implementation from the clean core.
2. Keep logos, business content, credentials and uploaded project media in the project implementation.
3. Merge a change back into the core only when it is reusable without project-specific assumptions.
4. Validate both fresh-install and cumulative-update database paths before merging.
5. Preserve backward compatibility whenever practical and document breaking changes explicitly.
