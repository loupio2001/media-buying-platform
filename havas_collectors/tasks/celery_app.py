from __future__ import annotations

import os

from celery import Celery
from celery.schedules import crontab


REDIS_URL = os.getenv("REDIS_URL", "redis://127.0.0.1:6379/0")

app = Celery("havas_collectors", broker=REDIS_URL, backend=REDIS_URL)
app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    timezone="Africa/Casablanca",
    enable_utc=False,
    task_track_started=True,
    task_acks_late=True,
    worker_prefetch_multiplier=1,
    imports=("havas_collectors.tasks.pull_tasks",),
)

app.conf.beat_schedule = {
    "pull-all-platforms-morning": {
        "task": "havas_collectors.tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour=7, minute=0),
    },
    "pull-all-platforms-midday": {
        "task": "havas_collectors.tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour=12, minute=0),
    },
    "pull-all-platforms-evening": {
        "task": "havas_collectors.tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour=18, minute=0),
    },
    "pull-all-platforms-night": {
        "task": "havas_collectors.tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour=23, minute=30),
    },
}
