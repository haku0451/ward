FROM golang:1.26 AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -o /ward-agent ./cmd/ward-agent

FROM gcr.io/distroless/static-debian12
COPY --from=build /ward-agent /ward-agent
USER nonroot:nonroot
ENTRYPOINT ["/ward-agent"]
CMD ["--config", "/etc/ward-agent/config.yaml"]
