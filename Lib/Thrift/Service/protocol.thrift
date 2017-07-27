namespace php ThriftClient
struct Request{
    1: string server,
    2: string method,
    3: binary body,
    4: map<string,string> header,
}

struct Response{
    1: i32 code,
    2: binary data,
}

service ThriftServer{
    Response Call(1:Request request),
}