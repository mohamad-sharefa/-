# gRPC contract

`inventory/v1/inventory.proto` is the only thing the main application and the inventory service share.

- **Inventory service (Node.js)** loads the `.proto` file at runtime with `@grpc/proto-loader`; nothing to generate.
- **Laravel** uses PHP classes generated from it into `backend/app/Grpc/Inventory/`. After changing the
  `.proto` file, regenerate them with [protoc](https://github.com/protocolbuffers/protobuf/releases) (v36):

  ```bash
  protoc --proto_path=proto --php_out=build proto/inventory/v1/inventory.proto
  cp -r build/App/Grpc/* backend/app/Grpc/
  ```

  `InventoryServiceClient.php` is written by hand (it is what `grpc_php_plugin` would generate);
  add a method there when you add an `rpc`.

The PHP runtime (`google/protobuf` 5.36) must match the protoc version that generated the classes.
