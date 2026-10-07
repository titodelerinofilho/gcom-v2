FROM postgres:17.6-alpine
RUN mkdir /backups && chown postgres:postgres /backups
COPY --chmod=755 docker/backup/*.sh /usr/local/bin/
USER postgres
ENTRYPOINT ["/usr/local/bin/schedule.sh"]
