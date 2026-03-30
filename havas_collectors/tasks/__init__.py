"""Celery tasks for scheduled collector pulls."""

from havas_collectors.tasks import pull_tasks

__all__ = ["pull_tasks"]